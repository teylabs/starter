<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Core creates an index on audit_events.workspace_id (audit_events_workspace_id_index), but a database that reached that column another way may not have one,
// and every workspace's audit query then scans the table. This adds it only when no index covers the column alone (a MySQL foreign key brings its own), so it
// never duplicates one. Additive: down() leaves it, as core's own migration owns the index on a fresh install.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_events') || ! Schema::hasColumn('audit_events', 'workspace_id') || Schema::hasIndex('audit_events', ['workspace_id'])) {
            return;
        }
        Schema::table('audit_events', function (Blueprint $t): void {
            $t->index('workspace_id', 'audit_events_workspace_id_index');
        });
    }

    public function down(): void
    {
        // Nothing: the index is core's on a fresh install, and dropping it here would take it from a host that had it all along.
    }
};
