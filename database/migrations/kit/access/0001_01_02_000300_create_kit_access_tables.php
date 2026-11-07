<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Workspaces\AdoptableTables;
use Tey\Kit\Features\Workspaces\Adoptions;

// A host that already has an entitlements or complimentary_grants table keeps it: the kit adopts a compatible one in place (AdoptableTables),
// creating, adding and changing nothing, and records the adoption (Adoptions). An incompatible one is refused, naming its columns, before
// anything is created. A table the host does not have is created.
return new class extends Migration
{
    public function up(): void
    {
        $adopted = AdoptableTables::adoptOrRefuse('access');
        if (! in_array('entitlements', $adopted, true)) {
            Schema::create('entitlements', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('workspace_id')->constrained();
                $t->string('kind');
                $t->timestamp('revoked_at')->nullable();
            });
        }
        if (! in_array('complimentary_grants', $adopted, true)) {
            Schema::create('complimentary_grants', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('workspace_id')->constrained();
                $t->unsignedBigInteger('granted_by');
                $t->string('intent_key');
                $t->string('fingerprint');
                $t->unsignedBigInteger('revoked_by')->nullable();
                $t->string('revocation_reason')->nullable();
                $t->unique(['granted_by', 'intent_key']);
                $t->string('reason');
                $t->timestamp('expires_at');
                $t->timestamp('revoked_at')->nullable();
            });
        }
        foreach ($adopted as $table) {
            Adoptions::record($table);
        }
    }

    public function down(): void
    {
        // An adopted table is the host's: it stays, with its rows, and only the record goes.
        foreach (['complimentary_grants', 'entitlements'] as $table) {
            Adoptions::adopted($table) ? Adoptions::forget($table) : Schema::dropIfExists($table);
        }
    }
};
