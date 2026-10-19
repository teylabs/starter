<?php

namespace App\Starter\Access;

use App\Models\User;
use App\Starter\Core\Principal;
use App\Starter\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Tey\Concierge\Concierge;

final class Access
{
    public function authorize(Principal $principal, string $ability, bool $paid = false): Workspace
    {
        if (! in_array($ability, ['settings.read', 'settings.write', 'notes.read', 'notes.write'], true)) {
            throw new \RuntimeException('permission_denied');
        }
        $workspace = Workspace::required();
        $user = User::query()->find($principal->actorId);
        $concierge = app(Concierge::class);
        $member = $user ? $concierge->membership($user, $workspace) : null;
        if (! $member?->isActive()) {
            throw new \RuntimeException('workspace_not_found');
        }
        if (! $concierge->account($user)->isActive()) {
            throw new \RuntimeException('account_inactive');
        }
        if (! $user->conciergeHasVerifiedEmail()) {
            throw new \RuntimeException('verification_required');
        }
        if (! $workspace->admitted) {
            throw new \RuntimeException('admission_required');
        }
        if (str_ends_with($ability, '.write') && ! $concierge->isOwner($member)) {
            throw new \RuntimeException('permission_denied');
        }
        if ($principal->connectionId !== null) {
            $connection = DB::table('connections')->where('id', $principal->connectionId)->where('actor_id', $principal->actorId)->where('workspace_id', $workspace->id)->whereNull('revoked_at')->first();
            if (! $connection || ! in_array($ability, json_decode($connection->scopes, true), true)) {
                throw new \RuntimeException('connection_denied');
            }
        }
        if (! Feature::for($workspace)->active(str_starts_with($ability, 'notes.') ? 'workspace.notes' : 'workspace.settings')) {
            throw new \RuntimeException('capability_unavailable');
        }
        if ($paid && ! $this->entitled($workspace)) {
            throw new \RuntimeException('entitlement_required');
        }

        return $workspace;
    }

    public function entitled(Workspace $workspace): bool
    {
        return DB::table('entitlements')->where('workspace_id', $workspace->id)->where('kind', 'paid')->whereNull('revoked_at')->exists()
            || DB::table('complimentary_grants')->where('workspace_id', $workspace->id)->whereNull('revoked_at')->where('expires_at', '>', now())->exists();
    }
}
