<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A connection approved on the consent screen is made before its access token exists: it waits on its OAuth client until the code is exchanged.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connections', function (Blueprint $t): void {
            $t->string('oauth_client_id', 100)->nullable()->after('oauth_token_id');
        });
    }

    public function down(): void
    {
        Schema::table('connections', function (Blueprint $t): void {
            $t->dropColumn('oauth_client_id');
        });
    }
};
