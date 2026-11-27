<?php

namespace App\Providers;

use App\Console\Commands\FirstOwnerCommand;
use App\Support\KitMigrationRepository;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\Query;
use Tey\Concierge\LocalAuth\LocalLogin;
use Tey\Kit\Features\SessionUi\ViewModels\LoginViewModel;

/** Register supported package resources; booting never executes migrations or seeds. */
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend('migration.repository', function ($repository, $app) {
            $migrations = $app['config']['database.migrations'];

            return new KitMigrationRepository($app['db'], is_array($migrations) ? ($migrations['table'] ?? null) : $migrations);
        });
    }

    public function boot(): void
    {
        // migrate:fresh, db:wipe and the like refuse outside the tests: a run against a missing .env.<name> falls back to .env and would wipe it.
        // A deliberate local reset sets the flag for that one command: ALLOW_DESTRUCTIVE_DB=1 php artisan migrate:fresh.
        // Nightwatch drops SQL on the database cache store's own tables and Telescope's: a cache read or a lock retry is otherwise a recorded query,
        // which is how one app spent millions of events on its own cache. Everything else, and every cache event, job and exception, is still kept.
        Nightwatch::rejectQueries(fn (Query $query): bool => preg_match('/\b(?:from|into|update)\s+"?(?:cache|cache_locks|telescope_entries|telescope_entries_tags|telescope_monitoring)"?(?=[\s(;]|$)/i', $query->sql) === 1);
        DB::prohibitDestructiveCommands(! $this->app->runningUnitTests() && ! config('database.allow_destructive'));
        // The template's own cache, tey/mod's discovery, warms and clears with `php artisan optimize` and `optimize:clear`.
        $this->optimizes('mod:cache', 'mod:clear', 'mod');
        $this->loadMigrationsFrom(InstalledVersions::getInstallPath('laravel/passport').'/database/migrations');
        $this->commands([FirstOwnerCommand::class]);
        // Laravel Cloud writes its attached buckets' disks at boot, after config/filesystems.php, so the R2 checksum mode is applied to every R2 disk here.
        foreach ((array) config('filesystems.disks') as $name => $disk) {
            if (($disk['driver'] ?? null) === 's3' && str_contains((string) ($disk['endpoint'] ?? ''), 'r2.cloudflarestorage.com')) {
                config(["filesystems.disks.$name.request_checksum_calculation" => $disk['request_checksum_calculation'] ?? 'when_required', "filesystems.disks.$name.response_checksum_validation" => $disk['response_checksum_validation'] ?? 'when_required']);
            }
        }
        // The kit's sign-in page takes the status and the local offer as extra props; this overrides session-ui's loginView with them. Concierge decides
        // whether the one-click local sign-in is offered: the offer is null wherever it isn't available. "Forgot your password?" is sent while Fortify's
        // resetPasswords feature is on (config/fortify.php), as the kit's own loginView sends it; the page draws no link without it.
        Fortify::loginView(fn () => Inertia::render('Auth/Login/Page', new LoginViewModel('Sign in', session('status'), app(LocalLogin::class)->offer()?->toArray(), forgot: Features::enabled(Features::resetPasswords()) ? '/forgot-password' : null)));
    }
}
