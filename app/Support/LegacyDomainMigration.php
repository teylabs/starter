<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Databases migrated before 0.3 ran the single `create_starter_domain_tables` migration; the per-feature migrations that replaced it skip there. */
final class LegacyDomainMigration
{
    public const ID = 'create_starter_domain_tables';

    public static function applied(): bool
    {
        $table = config('database.migrations.table', 'migrations');

        return Schema::hasTable($table) && DB::table($table)->where('migration', self::ID)->exists();
    }
}
