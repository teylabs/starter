<?php

namespace App\Support;

use Illuminate\Database\Migrations\DatabaseMigrationRepository;

/**
 * Databases migrated before 0.3 recorded the template's own migration ids. Before the migrator reads what has run,
 * the tey/kit migrations that create the same tables are recorded as run, in the latest batch, so `migrate` never
 * creates those tables twice.
 */
final class KitMigrationRepository extends DatabaseMigrationRepository
{
    private const CORE = '0001_01_02_000100_create_kit_core_tables';

    private const WORKSPACES = '0001_01_02_000200_create_kit_workspaces_table';

    private const PERMISSIONS = '0001_01_02_000201_create_permission_tables';

    private const OPERATORS = '0001_01_02_000202_create_kit_operators_table';

    private const ACCESS = '0001_01_02_000300_create_kit_access_tables';

    private const SETTINGS = '0001_01_02_000400_create_settings_table';

    private const SETTINGS_INTENTS = '0001_01_02_000401_create_kit_settings_tables';

    private const MCP = '0001_01_02_000600_create_kit_mcp_tables';

    private const NOTES = '0001_01_02_000700_create_kit_notes_tables';

    /** Template id => the ids that replace it. */
    public const MAP = [
        '0001_01_01_000000_create_starter_identity_tables' => ['0001_01_01_000000_create_users_table', self::WORKSPACES],
        '0001_01_01_000001_create_permission_tables' => [self::PERMISSIONS],
        '0001_01_01_000002_create_settings_table' => [self::SETTINGS],
        'create_starter_domain_tables' => [self::CORE, self::OPERATORS, self::ACCESS, self::SETTINGS_INTENTS, self::MCP, self::NOTES],
        'create_starter_core_tables' => [self::CORE],
        'create_starter_workspaces_tables' => [self::OPERATORS],
        'create_starter_access_tables' => [self::ACCESS],
        'create_starter_settings_tables' => [self::SETTINGS_INTENTS],
        'create_starter_mcp_tables' => [self::MCP],
        'create_starter_notes_tables' => [self::NOTES],
    ];

    public function getRan()
    {
        if ($this->repositoryExists()) {
            $this->getConnection()->transaction(function (): void {
                $ran = parent::getRan();
                $missing = [];
                foreach (self::MAP as $legacy => $ids) {
                    if (in_array($legacy, $ran, true)) {
                        array_push($missing, ...array_diff($ids, $ran, $missing));
                    }
                }
                sort($missing);
                $batch = $this->getLastBatchNumber();
                foreach ($missing as $id) {
                    $this->log($id, $batch);
                }
            });
        }

        return parent::getRan();
    }
}
