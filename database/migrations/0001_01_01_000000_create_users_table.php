<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The host's users table; every other table belongs to a kit feature or a package.
return new class extends Migration
{
    public function up(): void
    {
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
    }
};
