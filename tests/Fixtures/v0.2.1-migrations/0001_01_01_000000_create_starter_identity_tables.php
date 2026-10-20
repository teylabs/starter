<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('workspaces');
    }
};
