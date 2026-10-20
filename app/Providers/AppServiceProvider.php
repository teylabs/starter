<?php

namespace App\Providers;

use App\Console\FirstOwnerCommand;
use App\Support\KitMigrationRepository;
use App\Support\KitMigrator;
use App\Support\LoginResponse;
use Composer\InstalledVersions;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Tey\Concierge\LocalAuth\LocalLogin;

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
        $this->loadMigrationsFrom(InstalledVersions::getInstallPath('laravel/passport').'/database/migrations');
        $this->commands([FirstOwnerCommand::class]);
        // The kit's sign-in page takes the status and the local offer as extra props; this overrides session-ui's loginView with them. Concierge decides
        // whether the one-click local sign-in is offered: the offer is null wherever it isn't available.
        Fortify::loginView(fn () => Inertia::render('Auth/Login/Page', ['title' => 'Sign in', 'status' => session('status'), 'localLogin' => app(LocalLogin::class)->offer()]));
    }
}
