<?php

use Composer\InstalledVersions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Workspaces\AdoptableTables;
use Tey\Kit\Features\Workspaces\Adoptions;

// spatie/laravel-settings' table. A host that already has it keeps it: a compatible one (AdoptableTables) is adopted in place, with nothing
// created, added or changed, and the adoption recorded (Adoptions); an incompatible one is refused, naming its columns. Otherwise the locked
// package's own migration creates it.
return new class extends Migration
{
    public function up(): void
    {
        if (AdoptableTables::adoptOrRefuse('settings') !== []) {
            Adoptions::record(AdoptableTables::settingsTable());

            return;
        }
        (require InstalledVersions::getInstallPath('spatie/laravel-settings').'/database/migrations/create_settings_table.php.stub')->up();
    }

    public function down(): void
    {
        // An adopted settings table is the host's: it stays, with its rows, and only the record goes.
        $table = AdoptableTables::settingsTable();
        Adoptions::adopted($table) ? Adoptions::forget($table) : Schema::dropIfExists($table);
    }
};
