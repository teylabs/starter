<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class Audit
{
    public function write(int $actorId, int $workspaceId, string $event, array $details): void
    {
        DB::table('audit_events')->insert(['actor_id' => $actorId, 'workspace_id' => $workspaceId, 'event' => $event, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
