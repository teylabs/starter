<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('actor_id');
            // Core migrates alone: the workspaces feature adds the foreign key to `workspaces` when it is adopted.
            $t->unsignedBigInteger('workspace_id')->index();
            $t->string('event');
            $t->json('details');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
