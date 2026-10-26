<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the kit remembers about a person: their timezone, the onboarding steps they finished or skipped, and which welcome cards they dismissed.
        // `workspace_id` is 0 for state that is the person's own (a timezone) and a workspace's id for state that belongs to one (its welcome card).
        Schema::create('kit_user_state', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('workspace_id')->default(0);
            $t->string('key', 60);
            $t->text('value')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'workspace_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_user_state');
    }
};
