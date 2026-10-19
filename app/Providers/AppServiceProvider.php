<?php

namespace App\Providers;

use App\Console\FirstOwnerCommand;
use App\Support\KitMigrationRepository;
use Composer\InstalledVersions;
use Illuminate\Support\ServiceProvider;

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
        $this->loadMigrationsFrom(InstalledVersions::getInstallPath('laravel/passport').'/database/migrations');
        $this->commands([FirstOwnerCommand::class]);
    }
}
