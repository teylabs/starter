<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Workspaces\Adoptions;

// A workspace whose deletion was deferred to the host's job is closed at once: `deleting_at` is when, `deleting_by` who. Closed, it admits no one, takes no new
// members and is listed nowhere, until the host's job calls the kit's finish hook (docs/lifecycle-events.md). Existing rows stay open (null). An adopted host
// table that already has the columns keeps them.
return new class extends Migration
{
    public function up(): void
    {
        $missing = array_values(array_filter(['deleting_at', 'deleting_by'], fn (string $column): bool => ! Schema::hasColumn('workspaces', $column)));
        if ($missing === []) {
            return;
        }
        Schema::table('workspaces', function (Blueprint $t) use ($missing): void {
            if (in_array('deleting_at', $missing, true)) {
                $t->timestamp('deleting_at')->nullable();
            }
            if (in_array('deleting_by', $missing, true)) {
                $t->unsignedBigInteger('deleting_by')->nullable();
            }
        });
        if (Adoptions::adopted('workspaces')) {
            Adoptions::record('workspaces', $missing);
        }
    }

    public function down(): void
    {
        // On an adopted table a column goes only when the record says the kit added it.
        $columns = array_values(array_filter(['deleting_at', 'deleting_by'], fn (string $column): bool => Schema::hasColumn('workspaces', $column) && (! Adoptions::adopted('workspaces') || in_array($column, Adoptions::added('workspaces'), true))));
        if ($columns === []) {
            return;
        }
        Schema::table('workspaces', function (Blueprint $t) use ($columns): void {
            $t->dropColumn($columns);
        });
        Adoptions::forget('workspaces', $columns);
    }
};
