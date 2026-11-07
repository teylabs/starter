<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Core's `audit_events.workspace_id` is unconstrained so core can migrate without workspaces. Adopting workspaces adds the foreign key; a database that already has it is left as it is.
// Forward-only: down() changes nothing (see "Rolling back" in the kit's README).
return new class extends Migration
{
    private function constrained(): bool
    {
        foreach (Schema::getForeignKeys('audit_events') as $foreign) {
            if ($foreign['columns'] === ['workspace_id']) {
                return true;
            }
        }

        return false;
    }

    public function up(): void
    {
        if (! Schema::hasTable('audit_events') || $this->constrained()) {
            return;
        }
        // Audit rows naming a workspace that is gone would fail the constraint: leave it to 000207, which runs after 000206 clears them.
        $orphans = DB::table('audit_events')->whereNotNull('workspace_id')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('workspaces')->whereColumn('workspaces.id', 'audit_events.workspace_id'))->exists();
        if ($orphans) {
            return;
        }
        Schema::table('audit_events', function (Blueprint $t): void {
            $t->foreign('workspace_id')->references('id')->on('workspaces');
        });
    }

    public function down(): void
    {
        // The foreign key is part of what a full host's core migration created; leaving it is the safe direction.
    }
};
