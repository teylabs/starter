<?php

use App\Support\LegacyDomainMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (LegacyDomainMigration::applied()) {
            return;
        }
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
        if (LegacyDomainMigration::applied()) {
            return;
        }
        Schema::dropIfExists('audit_events');
    }
};
