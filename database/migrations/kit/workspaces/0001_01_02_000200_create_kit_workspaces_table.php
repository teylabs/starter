<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The workspaces table; the users table is the host's own.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('provisioning_invitation_id')->nullable()->unique();
            $t->unsignedInteger('settings_version')->default(1);
            $t->boolean('admitted')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
