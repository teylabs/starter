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
        Schema::create('connections', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->unsignedBigInteger('actor_id');
            $t->json('scopes');
            $t->string('oauth_token_id', 100)->nullable();
            $t->string('intent_key')->nullable();
            $t->string('fingerprint')->nullable();
            $t->unique(['actor_id', 'intent_key']);
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
        });
        Schema::create('entitlements', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->string('kind');
            $t->timestamp('revoked_at')->nullable();
        });
        Schema::create('operators', function (Blueprint $t): void {
            $t->unsignedBigInteger('actor_id')->primary();
        });
        Schema::create('complimentary_grants', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->unsignedBigInteger('granted_by');
            $t->string('intent_key');
            $t->string('fingerprint');
            $t->unsignedBigInteger('revoked_by')->nullable();
            $t->string('revocation_reason')->nullable();
            $t->unique(['granted_by', 'intent_key']);
            $t->string('reason');
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
        });
        Schema::create('settings_intents', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->unsignedBigInteger('actor_id');
            $t->string('intent_key');
            $t->string('fingerprint');
            $t->json('result');
            $t->unique(['workspace_id', 'actor_id', 'intent_key']);
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
        Schema::create('audit_events', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('actor_id');
            $t->foreignId('workspace_id')->constrained();
            $t->string('event');
            $t->json('details');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('note_intents');
        Schema::dropIfExists('settings_intents');
        Schema::dropIfExists('complimentary_grants');
        Schema::dropIfExists('operators');
        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('connections');
        Schema::dropIfExists('notes');
    }
};
