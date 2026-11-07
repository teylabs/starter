<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An account whose deletion was deferred to the host's job: closed at once (its Concierge account deactivated, sessions and tokens revoked) and
        // recorded here until the host's job calls the kit's finish hook, which deletes the user and this row. `workspace_ids` are the workspaces they left.
        // No foreign key: the row outlives nothing, and the finish hook removes it with the user.
        Schema::create('kit_account_deletions', function (Blueprint $t): void {
            $t->unsignedBigInteger('user_id')->primary();
            $t->text('workspace_ids');
            $t->timestamp('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_account_deletions');
    }
};
