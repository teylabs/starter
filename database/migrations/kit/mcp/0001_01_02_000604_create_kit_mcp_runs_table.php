<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_mcp_runs', function (Blueprint $t): void {
            $t->string('id', 40)->primary();
            $t->string('tool', 120);
            $t->string('class');
            $t->string('ability', 120);
            $t->unsignedBigInteger('actor_id');
            $t->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('workspace_id');
            $t->json('arguments');
            $t->string('intent_key', 120)->nullable();
            $t->string('status', 16);
            $t->string('step', 120)->nullable();
            $t->unsignedInteger('done')->nullable();
            $t->unsignedInteger('total')->nullable();
            $t->json('result')->nullable();
            $t->string('error', 64)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamp('expires_at')->index();
            $t->index(['actor_id', 'connection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_mcp_runs');
    }
};
