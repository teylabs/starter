<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Workspaces\Adoptions;

// The workspaces table; the users table is the host's own. A host that already has a workspaces table keeps it: the kit adopts
// it in place, adding only the columns it needs and never dropping or altering a host column, and records the adoption (Adoptions).
// With kit.workspaces.adopt on (kit:add records it when it plans the adoption), the table is the host's and this never creates it.
return new class extends Migration
{
    public function up(): void
    {
        // migrate --pretend runs no query, so hasTable() is false even for the host's table: with adopt on, show the adoption's SQL.
        if (Schema::hasTable('workspaces') || (config('kit.workspaces.adopt') === true && Schema::getConnection()->pretending())) {
            $this->adopt();

            return;
        }
        if (config('kit.workspaces.adopt') === true) {
            throw new RuntimeException('kit.workspaces.adopt is on, so the workspaces table is the host\'s and the kit never creates it, but it does not exist yet. Run the host\'s own workspaces migration first; see "Adopting into an existing workspaces table" in the kit\'s README.');
        }
        Schema::create('workspaces', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('provisioning_invitation_id')->nullable()->unique();
            $t->unsignedInteger('settings_version')->default(1);
            $t->boolean('admitted')->default(true);
        });
    }

    public function down(): void
    {
        // An adopted table is the host's: it stays, and only the columns this migration recorded adding are dropped.
        if (Adoptions::adopted('workspaces')) {
            $added = array_values(array_intersect(Adoptions::added('workspaces'), ['provisioning_invitation_id', 'settings_version', 'admitted']));
            foreach ($added as $column) {
                Schema::table('workspaces', function (Blueprint $t) use ($column): void {
                    if ($column === 'provisioning_invitation_id') {
                        $t->dropUnique([$column]);
                    }
                    $t->dropColumn($column);
                });
            }
            Adoptions::forget('workspaces');

            return;
        }
        Schema::dropIfExists('workspaces');
    }

    private function adopt(): void
    {
        $added = [];
        foreach (['provisioning_invitation_id', 'settings_version', 'admitted'] as $column) {
            if (Schema::hasColumn('workspaces', $column)) {
                continue;
            }
            Schema::table('workspaces', function (Blueprint $t) use ($column): void {
                match ($column) {
                    'provisioning_invitation_id' => $t->unsignedBigInteger($column)->nullable()->unique(),
                    'settings_version' => $t->unsignedInteger($column)->default(1),
                    'admitted' => $t->boolean($column)->default(true),
                };
            });
            // Every existing workspace is admitted, at settings version 1: set explicitly, not left to the column default.
            match ($column) {
                'settings_version' => DB::table('workspaces')->update([$column => 1]),
                'admitted' => DB::table('workspaces')->update([$column => true]),
                default => null,
            };
            $added[] = $column;
        }
        Adoptions::record('workspaces', $added);
    }
};
