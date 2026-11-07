<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Which OAuth clients were made by dynamic client registration (POST /oauth/register), so they can be told apart from a host's own public clients: the consent
// page marks them as unverified apps, and kit:mcp:clients revokes or prunes only these. A client registered before this table is adopted by hand (--adopt).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_mcp_registrations', function (Blueprint $t): void {
            $t->id();
            $t->string('oauth_client_id', 100)->unique();
            $t->string('application_type', 16)->default('web');
            $t->string('ip', 45)->nullable();
            $t->timestamp('registered_at');
            $t->timestamp('adopted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_mcp_registrations');
    }
};
