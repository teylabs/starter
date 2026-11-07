<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A workspace's mail identity (Settings → Email sending). No row means the defaults: the workspace's name as the sender, the platform address, replies to the sending member.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_mail_identities', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->unique()->constrained();
            $t->string('sender_name')->nullable();
            $t->string('from_mode')->default('platform');
            $t->string('domain')->nullable();
            $t->string('from_local')->default('hello');
            $t->string('provider_domain_id')->nullable();
            $t->string('domain_status')->default('none');
            $t->json('dns_records')->nullable();
            $t->timestamp('checked_at')->nullable();
            $t->string('reply_to_mode')->default('member');
            $t->string('reply_to_address')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_mail_identities');
    }
};
