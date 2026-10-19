<?php

use Illuminate\Support\Facades\DB;

// A real database failure on insert, for the rollback regression checks: a SQLite trigger, or its PostgreSQL twin.
if (! function_exists('failInserts')) {
    function failInserts(string $name, string $table, string $message, ?string $when = null): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE FUNCTION {$name}() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION '{$message}'; END \$\$ LANGUAGE plpgsql");
            DB::unprepared("CREATE TRIGGER {$name} BEFORE INSERT ON {$table} FOR EACH ROW".($when === null ? '' : " WHEN ({$when})")." EXECUTE FUNCTION {$name}()");

            return;
        }
        DB::statement("CREATE TRIGGER {$name} BEFORE INSERT ON {$table}".($when === null ? '' : " WHEN {$when}")." BEGIN SELECT RAISE(ABORT, '{$message}'); END");
    }

    function allowInserts(string $name, string $table): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("DROP TRIGGER {$name} ON {$table}; DROP FUNCTION {$name}()");

            return;
        }
        DB::statement("DROP TRIGGER {$name}");
    }
}
