<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

// Audit rows naming a workspace that no longer exists keep their history with a null workspace_id, so the foreign key (000207) can be added. The count
// is printed during `php artisan migrate` and logged.
// Forward-only: down() changes nothing (see "Rolling back" in the kit's README).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_events') || ! Schema::hasTable('workspaces')) {
            return;
        }
        $count = DB::table('audit_events')->whereNotNull('workspace_id')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('workspaces')->whereColumn('workspaces.id', 'audit_events.workspace_id'))->update(['workspace_id' => null]);
        if ($count > 0) {
            $message = "audit_events: set workspace_id to null on {$count} row(s) whose workspace no longer exists.";
            Log::warning($message, ['orphaned_audit_events' => $count]);
            if (app()->runningInConsole()) {
                echo $message.PHP_EOL;
            }
        }
    }

    public function down(): void
    {
        // The deleted workspaces' ids are not kept, so there is nothing to restore.
    }
};
