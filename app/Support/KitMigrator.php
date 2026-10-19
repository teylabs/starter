<?php

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;

/**
 * tey/kit 0.1.2 orders core's migration (audit_events, with a foreign key to workspaces) before the workspaces table.
 * SQLite accepts the dangling reference; PostgreSQL refuses it. Until the kit fixes its order, core runs right after
 * the workspaces table. Every other migration keeps Laravel's name order.
 */
final class KitMigrator extends Migrator
{
    private const CORE = '0001_01_02_000100_create_kit_core_tables';

    private const WORKSPACES = '0001_01_02_000200_create_kit_workspaces_table';

    public function getMigrationFiles($paths)
    {
        $files = parent::getMigrationFiles($paths);
        if (! isset($files[self::CORE], $files[self::WORKSPACES])) {
            return $files;
        }
        $core = $files[self::CORE];
        unset($files[self::CORE]);
        $ordered = [];
        foreach ($files as $name => $file) {
            $ordered[$name] = $file;
            if ($name === self::WORKSPACES) {
                $ordered[self::CORE] = $core;
            }
        }

        return $ordered;
    }
}
