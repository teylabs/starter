<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the finish hook ran for an account whose user row the host kept (anonymised through DeletesAccountRow): the row stays as the record that the
        // account is finished and closed. A hard-deleted account's row goes with the user.
        Schema::table('kit_account_deletions', function (Blueprint $t): void {
            $t->timestamp('finished_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kit_account_deletions', function (Blueprint $t): void {
            $t->dropColumn('finished_at');
        });
    }
};
