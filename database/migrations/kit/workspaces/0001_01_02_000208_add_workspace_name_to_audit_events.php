<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The workspace's name when the event was written, so an audit row still says which workspace it was after the workspace is deleted. Existing rows
// are filled from their workspace; core's Audit fills it on every row it writes while the column exists.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_events') || ! Schema::hasTable('workspaces')) {
            return;
        }
        if (! Schema::hasColumn('audit_events', 'workspace_name')) {
            Schema::table('audit_events', function (Blueprint $t): void {
                $t->string('workspace_name')->nullable();
            });
        }
        DB::table('audit_events')->whereNull('workspace_name')->whereNotNull('workspace_id')->update([
            'workspace_name' => DB::table('workspaces')->select('name')->whereColumn('workspaces.id', 'audit_events.workspace_id')->limit(1),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('audit_events', 'workspace_name')) {
            Schema::table('audit_events', function (Blueprint $t): void {
                $t->dropColumn('workspace_name');
            });
        }
    }
};
