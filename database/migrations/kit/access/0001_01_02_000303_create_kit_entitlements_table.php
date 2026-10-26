<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a workspace's plan includes, per entitlement: whether it is included and its limits (`{"seats": 5}`, a null limit is unlimited). A billing integration writes these
        // rows and nothing else; a workspace without a row follows the app's default (`kit.entitlements`). Separate from capabilities (is the feature on here) and permissions
        // (may this person do it).
        Schema::create('kit_entitlements', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->string('entitlement', 120);
            $t->boolean('included');
            $t->json('limits')->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'entitlement']);
            $t->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_entitlements');
    }
};
