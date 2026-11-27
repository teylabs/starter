<?php

use Illuminate\Database\Migrations\Migration;
use Tey\Kit\Features\Workspaces\Rls;

/**
 * Row-level security for the kit's workspace tables that config/kit.php doesn't list in kit.tenancy.rls.except. A no-op while RLS is off or off PostgreSQL.
 */
return new class extends Migration
{
    private const TABLES = ['settings_intents', 'capability_overrides', 'plan_entitlements', 'entitlements', 'complimentary_grants'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Rls::protect($table);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Rls::unprotect($table);
        }
    }
};
