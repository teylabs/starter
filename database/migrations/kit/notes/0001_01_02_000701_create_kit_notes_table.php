<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kit-prefixed, so a host's own `notes` (an earlier kit release's) or `app_notes` table never collides: `kit:notes:import` copies from those.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_notes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->unsignedBigInteger('author_id')->nullable();
            // A registered subject alias (Registry::noteSubject) and its id; null for a note on the workspace itself.
            $t->string('subject_type', 64)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->text('body');
            $t->string('colour', 32);
            $t->boolean('pinned')->default(false);
            $t->unsignedInteger('version')->default(1);
            // Where an imported note came from (`notes:42`), so a second import skips it.
            $t->string('imported_from', 120)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['workspace_id', 'pinned', 'created_at', 'id']);
            $t->index(['workspace_id', 'subject_type', 'subject_id']);
            $t->unique(['workspace_id', 'imported_from']);
        });
        Schema::create('kit_note_intents', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->unsignedBigInteger('actor_id');
            $t->string('intent_key', 120);
            $t->string('fingerprint', 64);
            $t->json('result');
            $t->timestamp('created_at')->nullable();
            $t->unique(['workspace_id', 'actor_id', 'intent_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_note_intents');
        Schema::dropIfExists('kit_notes');
    }
};
