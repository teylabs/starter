<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->string('kind');
            $t->timestamp('revoked_at')->nullable();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('complimentary_grants');
        Schema::dropIfExists('entitlements');
    }
};
