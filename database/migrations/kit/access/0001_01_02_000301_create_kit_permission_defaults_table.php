<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Which default grants kit:permissions:sync has applied, so a default someone removed is not granted again.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_permission_defaults', function (Blueprint $t): void {
            $t->id();
            $t->string('permission');
            $t->string('role');
            $t->timestamp('applied_at');
            $t->unique(['permission', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_permission_defaults');
    }
};
