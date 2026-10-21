<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The kit's own bookkeeping, one row per fact: today the fingerprint of the role and permission catalog the last deploy synced.
        Schema::create('kit_meta', function (Blueprint $t): void {
            $t->string('key')->primary();
            $t->text('value');
            $t->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_meta');
    }
};
