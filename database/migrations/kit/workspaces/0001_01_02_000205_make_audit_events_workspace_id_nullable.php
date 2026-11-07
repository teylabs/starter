<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit rows outlive their workspace: deleting a workspace sets their workspace_id to null (000207), so the column takes null. Core alone keeps it
// required, since every row core writes names a workspace.
// Forward-only: down() changes nothing (see "Rolling back" in the kit's README).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_events')) {
            return;
        }
        foreach (Schema::getColumns('audit_events') as $column) {
            if ($column['name'] === 'workspace_id' && $column['nullable']) {
                return;
            }
        }
        Schema::table('audit_events', function (Blueprint $t): void {
            $t->unsignedBigInteger('workspace_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows whose workspace was deleted hold null: making the column required again would fail on them, so it stays nullable.
    }
};
