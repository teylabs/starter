<?php

namespace App\Support;

use Composer\InstalledVersions;
use Illuminate\Support\ServiceProvider;

/** Register supported package resources; booting never executes migrations or seeds. */
final class InstallProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(InstalledVersions::getInstallPath('laravel/passport').'/database/migrations');
    }
}
