<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A capability switched on or off in one workspace: for a person (their user id) or for the whole workspace (`user_id` 0). Precedence is the person's row, then the
        // workspace's, then the capability's own default (the configured one, or a plan's, through Pennant); clearing a row puts them back one level up. Kept here, not in
        // Pennant's store, because Pennant's default store forgets its values on every request.
        Schema::create('kit_capability_overrides', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('user_id');
            $t->string('capability', 120);
            $t->boolean('enabled');
            $t->timestamps();
            $t->unique(['workspace_id', 'user_id', 'capability']);
            $t->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_capability_overrides');
    }
};
