<?php

require __DIR__.'/environment.php';
require dirname(__DIR__).'/bootstrap/app.php';
require dirname(__DIR__).'/database/fixture.php';
use App\Adapters\SettingsUi;
use App\Models\Workspace;
use App\Operations\ComplimentaryAccess;
use App\Support\Access;
use App\Support\Principal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

$failed = [];
$tests = [
    'settings rejects stale expected version' => function (): void {
        try {
            app(SettingsUi::class)->update(new Principal(1), 1, ['display_name' => 'stale', 'timezone' => 'UTC', 'expected_version' => 2, 'intent_key' => 'stale-test']);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'stale_settings') {
                return;
            }
            throw $e;
        }
        throw new RuntimeException('Stale edit accepted');
    },
    'grant repeat has one row and audit' => function (): void {
        $operation = app(ComplimentaryAccess::class);
        $expiry = now()->addDay();
        $first = $operation->grant(new Principal(99), 1, 'intent test', $expiry, 'grant-repeat');
        $repeat = $operation->grant(new Principal(99), 1, 'intent test', $expiry, 'grant-repeat');
        if ($first !== $repeat || DB::table('complimentary_grants')->count() !== 1 || DB::table('audit_events')->count() !== 1) {
            throw new RuntimeException('Duplicate grant committed');
        }
    },
];
$tests += [
    'settings same intent is one commit and changed intent conflicts' => function (): void {
        $ui = app(SettingsUi::class);
        $actor = new Principal(1);
        $input = ['display_name' => 'Intent Alpha', 'timezone' => 'UTC', 'expected_version' => 1, 'intent_key' => 'settings-repeat'];
        $first = $ui->update($actor, 1, $input);
        if ($first['version'] !== 2) {
            throw new RuntimeException('Missing authoritative version in output');
        }
        if ($first !== $ui->update($actor, 1, $input) || (int) DB::table('workspaces')->where('id', 1)->value('settings_version') !== 2 || DB::table('settings_intents')->count() !== 1) {
            throw new RuntimeException('Duplicate settings write');
        }
        try {
            $ui->update($actor, 1, [...$input, 'display_name' => 'changed']);
            throw new RuntimeException('Changed intent accepted');
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'intent_conflict') {
                throw $e;
            }
        }
        // Revoked actors cannot recover a previous success receipt.
        DB::table('concierge_memberships')->where('workspace_id', 1)->where('user_id', 1)->update(['status' => 'suspended']);
        try {
            $ui->update($actor, 1, $input);
            throw new RuntimeException('Revoked retry accepted');
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'workspace_not_found') {
                throw $e;
            }
        }
        DB::table('concierge_memberships')->where('workspace_id', 1)->where('user_id', 1)->update(['status' => 'active']);
    },
    'active grant cannot be replaced and revoked retry never reactivates' => function (): void {
        $op = app(ComplimentaryAccess::class);
        $operator = new Principal(99);
        $grant = DB::table('complimentary_grants')->first();
        $expiry = new DateTimeImmutable($grant->expires_at);
        foreach ([['changed', $expiry, 'grant-repeat', 'intent_conflict'], ['intent test', $expiry, 'other-key', 'active_grant_exists']] as [$reason, $until, $key, $expected]) {
            try {
                $op->grant($operator, 1, $reason, $until, $key);
                throw new RuntimeException('Expected grant refusal');
            } catch (RuntimeException $e) {
                if ($e->getMessage() !== $expected) {
                    throw $e;
                }
            }
        }
        $op->revoke($operator, $grant->id, 'revoke original');
        $op->revoke($operator, $grant->id, 'retry reason must not overwrite');
        if ($op->grant($operator, 1, 'intent test', $expiry, 'grant-repeat') !== $grant->id) {
            throw new RuntimeException('Retry changed identity');
        }
        if (app(Access::class)->entitled(Workspace::findOrFail(1))) {
            throw new RuntimeException('Revoked retry entitled');
        }
        if (DB::table('complimentary_grants')->value('revocation_reason') !== 'revoke original') {
            throw new RuntimeException('Revocation history overwritten');
        }
        $new = $op->grant($operator, 1, 'explicit new period', now()->addDays(2), 'new-period');
        if ($new === $grant->id) {
            throw new RuntimeException('Regrant overwrote history');
        }
        DB::table('complimentary_grants')->where('id', $new)->update(['expires_at' => now()->subDay()]);
        if (app(Access::class)->entitled(Workspace::findOrFail(1))) {
            throw new RuntimeException('Expired grant entitled');
        }
    },
    'audit failure preserves active grant during revoke' => function (): void {
        $op = app(ComplimentaryAccess::class);
        $operator = new Principal(99);
        $id = $op->grant($operator, 1, 'audit revoke fixture', now()->addDay(), 'revoke-audit');
        $before = DB::table('complimentary_grants')->get()->toJson();
        DB::statement("CREATE TRIGGER fail_audit BEFORE INSERT ON audit_events BEGIN SELECT RAISE(ABORT, 'synthetic_audit_failure'); END");
        try {
            $op->revoke($operator, $id, 'must rollback');
            throw new RuntimeException('Expected audit failure');
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'synthetic_audit_failure')) {
                throw $e;
            }
        } finally {
            DB::statement('DROP TRIGGER fail_audit');
        }
        if ($before !== DB::table('complimentary_grants')->get()->toJson()) {
            throw new RuntimeException('Failed revoke persisted');
        }
    },
];
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS $name\n";
    } catch (Throwable $e) {
        $failed[] = $name;
        echo "FAIL $name: {$e->getMessage()}\n";
    }
}
exit($failed === [] ? 0 : 1);
