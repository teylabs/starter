<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Workspaces\Slugs;

// A workspace's slug is the first segment of its pages. Existing workspaces get one made from their name.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $t): void {
            $t->string('slug', 63)->nullable()->unique();
        });
        foreach (DB::table('workspaces')->orderBy('id')->get() as $workspace) {
            DB::table('workspaces')->where('id', $workspace->id)->update(['slug' => Slugs::unique((string) $workspace->name)]);
        }
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $t): void {
            $t->dropUnique(['slug']);
            $t->dropColumn('slug');
        });
    }
};
