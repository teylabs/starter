<?php

namespace App\Starter\Access\Operations;

use App\Models\User;
use App\Starter\Core\Audit;
use App\Starter\Core\Principal;
use App\Starter\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Tey\Concierge\Concierge;

final class ComplimentaryAccess
{
    public function grant(Principal $operator, int $workspaceId, string $reason, \DateTimeInterface $expiresAt, string $intentKey): int
    {
        $this->operator($operator, $reason);
        if ($intentKey === '' || strlen($intentKey) > 120) {
            throw new \RuntimeException('intent_key_required');
        }
        $expiry = $expiresAt->format('Y-m-d H:i:s');
        $fingerprint = hash('sha256', json_encode([$workspaceId, $reason, $expiry], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($operator, $workspaceId, $reason, $expiry, $intentKey, $fingerprint): int {
            Workspace::query()->whereKey($workspaceId)->lockForUpdate()->firstOrFail();
            $previous = DB::table('complimentary_grants')->where('granted_by', $operator->actorId)->where('intent_key', $intentKey)->first();
            if ($previous) {
                if ($previous->fingerprint !== $fingerprint) {
                    throw new \RuntimeException('intent_conflict');
                }

                // A retry never reactivates a revoked or expired grant.
                return (int) $previous->id;
            }
            if ($expiry <= now()->format('Y-m-d H:i:s')) {
                throw new \RuntimeException('future_expiry_required');
            }
            // One active complimentary period per workspace. New intent cannot
            // silently replace or extend it; revoke explicitly before regranting.
            if (DB::table('complimentary_grants')->where('workspace_id', $workspaceId)->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
                throw new \RuntimeException('active_grant_exists');
            }
            $id = DB::table('complimentary_grants')->insertGetId(['workspace_id' => $workspaceId, 'granted_by' => $operator->actorId, 'reason' => $reason, 'expires_at' => $expiry, 'revoked_at' => null, 'intent_key' => $intentKey, 'fingerprint' => $fingerprint]);
            app(Audit::class)->write($operator->actorId, $workspaceId, 'complimentary.granted', ['grant_id' => $id, 'reason' => $reason, 'expires_at' => $expiry]);

            return $id;
        });
    }

    public function revoke(Principal $operator, int $grantId, string $reason): void
    {
        $this->operator($operator, $reason);
        DB::transaction(function () use ($operator, $grantId, $reason): void {
            $grant = DB::table('complimentary_grants')->where('id', $grantId)->first();
            if (! $grant) {
                throw new \RuntimeException('grant_not_found');
            }
            // Same lock order as grant: workspace then grant.
            Workspace::query()->whereKey($grant->workspace_id)->lockForUpdate()->firstOrFail();
            $grant = DB::table('complimentary_grants')->where('id', $grantId)->lockForUpdate()->first();
            if ($grant->revoked_at !== null) {
                return;
            }
            DB::table('complimentary_grants')->where('id', $grantId)->update(['revoked_at' => now(), 'revoked_by' => $operator->actorId, 'revocation_reason' => $reason]);
            app(Audit::class)->write($operator->actorId, $grant->workspace_id, 'complimentary.revoked', ['grant_id' => $grantId, 'reason' => $reason]);
        });
    }

    private function operator(Principal $operator, string $reason): void
    {
        if ($operator->connectionId !== null || ! DB::table('operators')->where('actor_id', $operator->actorId)->exists()) {
            throw new \RuntimeException('operator_required');
        }
        $user = User::find($operator->actorId);
        if (! $user || ! $user->conciergeHasVerifiedEmail() || ! app(Concierge::class)->account($user)->isActive()) {
            throw new \RuntimeException('operator_required');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('reason_required');
        }
    }
}
