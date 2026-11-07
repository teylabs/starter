<?php

namespace App\Providers;

use App\Console\Commands\FirstOwnerCommand;
use App\Support\KitMigrationRepository;
use App\Support\KitMigrator;
use App\Support\LoginResponse;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
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
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->extend('migrator', fn ($migrator, $app) => new KitMigrator($app['migration.repository'], $app['db'], $app['files'], $app['events']));
    }

    public function boot(): void
    {
        // migrate:fresh, db:wipe and the like refuse outside the tests: a run against a missing .env.<name> falls back to .env and would wipe it.
        // A deliberate local reset sets the flag for that one command: ALLOW_DESTRUCTIVE_DB=1 php artisan migrate:fresh.
        DB::prohibitDestructiveCommands(! $this->app->runningUnitTests() && ! config('database.allow_destructive'));
        // The template's own cache, tey/mod's discovery, warms and clears with `php artisan optimize` and `optimize:clear`.
        $this->optimizes('mod:cache', 'mod:clear', 'mod');
        $this->loadMigrationsFrom(InstalledVersions::getInstallPath('laravel/passport').'/database/migrations');
        $this->commands([FirstOwnerCommand::class]);
        // The kit's sign-in page takes the status and the local offer as extra props; this overrides session-ui's loginView with them. Concierge decides
        // whether the one-click local sign-in is offered: the offer is null wherever it isn't available.
        Fortify::loginView(fn () => Inertia::render('Auth/Login/Page', new LoginViewModel('Sign in', session('status'), app(LocalLogin::class)->offer()?->toArray())));
    }
}
