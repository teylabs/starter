<?php

use Composer\InstalledVersions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Workspaces\Adoptions;

// Use the locked package's published migration, including its configured team keys. A host that already has spatie/laravel-permission's
// tables keeps them: the kit adopts them in place and creates nothing, once their teams column matches the configured one. With
// kit.access.adopt on (kit:add records it when it plans the adoption), the tables are the host's and this never creates them.
return new class(require InstalledVersions::getInstallPath('spatie/laravel-permission').'/database/migrations/create_permission_tables.php.stub') extends Migration
{
    public function __construct(private readonly Migration $package) {}

    public function up(): void
    {
        $tables = array_intersect_key((array) config('permission.table_names'), array_flip(['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions']));
        $present = array_filter($tables, fn (string $table): bool => Schema::hasTable($table));
        // migrate --pretend runs no query, so hasTable() finds none of the host's tables: with adopt on, show the adoption's SQL.
        if (config('kit.access.adopt') === true && Schema::getConnection()->pretending()) {
            foreach ($tables as $table) {
                Adoptions::record($table);
            }

            return;
        }
        if ($present === [] && config('kit.access.adopt') === true) {
            throw new RuntimeException('kit.access.adopt is on, so spatie/laravel-permission\'s tables are the host\'s and the kit never creates them, but they do not exist yet. Run the host\'s own permission migration first; see "Adopting into an existing workspaces table" in the kit\'s README.');
        }
        if ($present === []) {
            if (! method_exists($this->package, 'up')) {
                throw new RuntimeException('spatie/laravel-permission\'s published permission migration has no up() method.');
            }
            $this->package->up();

            return;
        }
        if (count($present) !== 5) {
            throw new RuntimeException('The host database has some of the permission tables ('.implode(', ', $present).') but not all of '.implode(', ', $tables).'; the kit adopts all five or creates all five.');
        }
        if (config('permission.teams')) {
            $team = (string) config('permission.column_names.team_foreign_key');
            $missing = array_values(array_filter([$tables['roles'], $tables['model_has_permissions'], $tables['model_has_roles']], fn (string $table): bool => ! Schema::hasColumn($table, $team)));
            if ($missing !== []) {
                throw new RuntimeException('permission.teams is on, but the existing '.implode(', ', $missing).' table(s) have no '.$team.' column (permission.column_names.team_foreign_key). Set team_foreign_key in config/permission.php to the host\'s teams column, or run php artisan permission:setup-teams and migrate first.');
            }
        }
        foreach ($tables as $table) {
            Adoptions::record($table);
        }
    }

    public function down(): void
    {
        // Adopted permission tables are the host's: they stay, and only the record goes.
        $tables = array_intersect_key((array) config('permission.table_names'), array_flip(['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions']));
        if (Adoptions::adopted((string) $tables['roles'])) {
            foreach ($tables as $table) {
                Adoptions::forget($table);
            }

            return;
        }
        if (! method_exists($this->package, 'down')) {
            throw new RuntimeException('spatie/laravel-permission\'s published permission migration has no down() method.');
        }
        $this->package->down();
    }
};
