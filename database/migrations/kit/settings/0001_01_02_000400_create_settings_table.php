<?php

use Composer\InstalledVersions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        (require InstalledVersions::getInstallPath('spatie/laravel-settings').'/database/migrations/create_settings_table.php.stub')->up();
    }

    public function down(): void
    {
        Schema::dropIfExists(config('settings.repositories.database.table') ?? 'settings');
    }
};
