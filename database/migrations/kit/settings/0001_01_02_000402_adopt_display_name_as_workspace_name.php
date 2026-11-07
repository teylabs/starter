<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Before 0.4.20 General saved the name to the `display_name` setting while the shell showed `workspaces.name`. The name is now the workspace's own, so a
// name someone set on General becomes the workspace's name (and its settings version moves). An empty or equal value is skipped, and a rerun finds nothing.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasTable('workspaces')) {
            return;
        }
        foreach (DB::table('settings')->where('group', 'like', 'workspace.%')->where('name', 'display_name')->get() as $row) {
            $id = substr((string) $row->group, strlen('workspace.'));
            $value = json_decode((string) $row->payload, true);
            $name = is_string($value) ? trim($value) : '';
            if (! ctype_digit($id) || $name === '') {
                continue;
            }
            DB::transaction(function () use ($id, $name): void {
                $workspace = DB::table('workspaces')->where('id', (int) $id)->lockForUpdate()->first();
                if ($workspace !== null && $workspace->name !== $name) {
                    DB::table('workspaces')->where('id', (int) $id)->update(['name' => $name, 'settings_version' => (int) $workspace->settings_version + 1]);
                }
            });
        }
    }

    public function down(): void
    {
        // Nothing to undo: the name stays the one set on General, which the setting still mirrors.
    }
};
