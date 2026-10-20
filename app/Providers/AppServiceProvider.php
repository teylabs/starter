<?php

namespace App\Providers;

use App\Console\FirstOwnerCommand;
use App\Support\KitMigrationRepository;
use App\Support\KitMigrator;
use Composer\InstalledVersions;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

/** Register supported package resources; booting never executes migrations or seeds. */
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend('migration.repository', function ($repository, $app) {
            $migrations = $app['config']['database.migrations'];

            return new KitMigrationRepository($app['db'], is_array($migrations) ? ($migrations['table'] ?? null) : $migrations);
        });
        $this->app->extend('migrator', fn ($migrator, $app) => new KitMigrator($app['migration.repository'], $app['db'], $app['files'], $app['events']));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(InstalledVersions::getInstallPath('laravel/passport').'/database/migrations');
        $this->commands([FirstOwnerCommand::class]);
        // The designed sign-in page replaces session-ui's unstyled starting point; it boots after the kit.
        Fortify::loginView(fn () => Inertia::render('Auth/SignIn/Page', ['title' => 'Sign in', 'status' => session('status')]));
    }
}
