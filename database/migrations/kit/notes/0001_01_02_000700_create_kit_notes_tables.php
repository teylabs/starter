<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->string('body', 2000);
            $t->unsignedInteger('version')->default(1);
        });
        Schema::create('note_intents', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->unsignedBigInteger('actor_id');
            $t->string('intent_key');
            $t->string('fingerprint');
            $t->json('result');
            $t->unique(['workspace_id', 'actor_id', 'intent_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_intents');
        Schema::dropIfExists('notes');
    }
};
