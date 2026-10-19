<?php

use App\Starter\Core\LegacyDomainMigration;
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
        Schema::create('operators', function (Blueprint $t): void {
            $t->unsignedBigInteger('actor_id')->primary();
        });
    }

    public function down(): void
    {
        if (LegacyDomainMigration::applied()) {
            return;
        }
        Schema::dropIfExists('operators');
    }
};
