<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
