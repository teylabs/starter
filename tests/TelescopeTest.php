<?php

use App\Models\User;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;

test('only an operator opens telescope, never a workspace owner by email', function () {
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
    $operator = User::query()->forceCreate(['name' => 'Operator', 'email' => 'operator@example.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
    $owner = User::query()->forceCreate(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
    DB::table('operators')->insert(['actor_id' => $operator->getKey()]);

    expect(Gate::forUser($operator)->allows('viewTelescope'))->toBeTrue();
    expect(Gate::forUser($owner)->allows('viewTelescope'))->toBeFalse();
    expect(Gate::allows('viewTelescope'))->toBeFalse('a guest');
    expect((string) file_get_contents(app_path('Providers/TelescopeServiceProvider.php')))->not->toMatch('/[\w.+-]+@[\w-]+\.[\w.]+/', 'no addresses in the gate');
});

test('in production telescope keeps the steady-state entries and drops the rest and the cache SQL', function () {
    $entry = fn (string $type, array $content): IncomingEntry => tap(IncomingEntry::make($content), fn (IncomingEntry $e) => $e->type($type));

    expect(TelescopeServiceProvider::keeps($entry(EntryType::REQUEST, ['response_status' => 200]), false))->toBeFalse();
    expect(TelescopeServiceProvider::keeps($entry(EntryType::REQUEST, ['response_status' => 404]), false))->toBeTrue();
    expect(TelescopeServiceProvider::keeps($entry(EntryType::REQUEST, ['response_status' => 500]), false))->toBeTrue();
    expect(TelescopeServiceProvider::keeps($entry(EntryType::QUERY, ['sql' => 'select * from "users"', 'slow' => false]), false))->toBeFalse();
    expect(TelescopeServiceProvider::keeps($entry(EntryType::QUERY, ['sql' => 'select * from "users"', 'slow' => true]), false))->toBeTrue();
    expect(TelescopeServiceProvider::keeps($entry(EntryType::LOG, ['level' => 'warning']), false))->toBeTrue();

    // Everything is kept off production, except the cache's and Telescope's own SQL, even when slow.
    expect(TelescopeServiceProvider::keeps($entry(EntryType::REQUEST, ['response_status' => 200]), true))->toBeTrue();
    expect(TelescopeServiceProvider::keeps($entry(EntryType::QUERY, ['sql' => 'update "cache_locks" set "owner" = ?', 'slow' => true]), true))->toBeFalse();
    expect(TelescopeServiceProvider::keeps($entry(EntryType::QUERY, ['sql' => 'insert into "telescope_entries" ("uuid") values (?)', 'slow' => false]), true))->toBeFalse();

    $watchers = config('telescope.watchers');
    foreach (['Cache', 'Redis', 'Model', 'View', 'Event'] as $noisy) {
        $setting = $watchers['Laravel\\Telescope\\Watchers\\'.$noisy.'Watcher'];
        expect(is_array($setting) ? $setting['enabled'] : $setting)->toBeFalse($noisy);
    }
    $commands = collect(app(Schedule::class)->events())->map(fn ($event) => (string) $event->command)->implode("\n");
    expect($commands)->toContain('telescope:prune')->toContain('--hours=48');
});
