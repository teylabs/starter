<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Deleting a workspace keeps its audit rows with a null workspace_id. A foreign key on workspace_id without ON DELETE SET NULL (000204's) is dropped and
// added again with it; one that already sets null is left as it is. Only the workspace_id key is touched.
// Forward-only: down() changes nothing (see "Rolling back" in the kit's README).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_events') || ! Schema::hasTable('workspaces')) {
            return;
        }
        $existing = null;
        foreach (Schema::getForeignKeys('audit_events') as $foreign) {
            if ($foreign['columns'] === ['workspace_id']) {
                $existing = $foreign;
            }
        }
        if ($existing !== null && strtolower((string) $existing['on_delete']) === 'set null') {
            return;
        }
        Schema::table('audit_events', function (Blueprint $t) use ($existing): void {
            if ($existing !== null) {
                $t->dropForeign($existing['name'] !== null && $existing['name'] !== '' ? $existing['name'] : ['workspace_id']);
            }
        });
        Schema::table('audit_events', function (Blueprint $t): void {
            $t->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // A key without ON DELETE SET NULL would block deleting a workspace that has audit rows again: the key stays.
    }
};
