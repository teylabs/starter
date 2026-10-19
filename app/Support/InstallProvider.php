<?php

namespace App\Support;

use Composer\InstalledVersions;
use Illuminate\Support\ServiceProvider;
use Tey\StarterBlueprint\LifecycleHost;

/** Register supported package resources; booting never executes migrations or seeds. */
final class InstallProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LifecycleHost::class, fn () => new StarterLifecycle);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(InstalledVersions::getInstallPath('laravel/passport').'/database/migrations');
    }
}
