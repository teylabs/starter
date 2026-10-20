<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A user's own security history (sign-ins, failed sign-ins, password and two-factor changes). It belongs to the user, never to a workspace, and is kept.
        Schema::create('kit_security_events', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('type', 40);
            $t->string('ip', 45)->nullable();
            $t->string('agent', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_security_events');
    }
};
