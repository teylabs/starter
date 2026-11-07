<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Workspaces\Adoptions;
use Tey\Kit\Features\Workspaces\Slugs;

// A workspace's slug is the first segment of its pages. Existing workspaces without one get one made from their name. An adopted
// host table that already has a slug column keeps it, and its slugs.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('workspaces', 'slug')) {
            Schema::table('workspaces', function (Blueprint $t): void {
                $t->string('slug', 63)->nullable()->unique();
            });
            if (Adoptions::adopted('workspaces')) {
                Adoptions::record('workspaces', ['slug']);
            }
        }
        foreach (DB::table('workspaces')->where(fn ($query) => $query->whereNull('slug')->orWhere('slug', ''))->orderBy('id')->get() as $workspace) {
            DB::table('workspaces')->where('id', $workspace->id)->update(['slug' => Slugs::unique((string) $workspace->name)]);
        }
    }

    public function down(): void
    {
        // On an adopted table the slug column goes only when the record says the kit added it.
        if (Adoptions::adopted('workspaces') && ! in_array('slug', Adoptions::added('workspaces'), true)) {
            return;
        }
        Schema::table('workspaces', function (Blueprint $t): void {
            $t->dropUnique(['slug']);
            $t->dropColumn('slug');
        });
        Adoptions::forget('workspaces', ['slug']);
    }
};
