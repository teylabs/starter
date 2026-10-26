<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_mcp_intents', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('actor_id');
            $t->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $t->string('intent_key', 120);
            $t->string('fingerprint', 64);
            $t->json('result');
            $t->timestamp('created_at')->nullable();
            $t->timestamp('expires_at')->index();
            $t->unique(['actor_id', 'connection_id', 'intent_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_mcp_intents');
    }
};
