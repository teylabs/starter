<?php

use Composer\InstalledVersions;

// Use the locked package's published migration, including its configured team keys.
return require InstalledVersions::getInstallPath('spatie/laravel-permission').'/database/migrations/create_permission_tables.php.stub';
