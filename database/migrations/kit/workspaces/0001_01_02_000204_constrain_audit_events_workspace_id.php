<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Core's `audit_events.workspace_id` is unconstrained so core can migrate without workspaces. Adopting workspaces adds the foreign key; a database that already has it is left as it is.
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
        Schema::table('audit_events', function (Blueprint $t): void {
            $t->foreign('workspace_id')->references('id')->on('workspaces');
        });
    }

    public function down(): void
    {
        // The foreign key is part of what a full host's core migration created; leaving it is the safe direction.
    }
};
