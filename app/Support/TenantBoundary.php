<?php

namespace App\Support;

use App\Models\Workspace;
use Laravel\Pennant\Feature;
use Tey\Concierge\Concierge;

final class TenantBoundary
{
    public function run(int $workspaceId, callable $operation): mixed
    {
        $workspace = Workspace::query()->findOrFail($workspaceId);
        try {
            return $workspace->execute(fn () => app(Concierge::class)->context()->run($workspace, \Closure::fromCallable($operation)));
        } finally {
            Feature::flushCache();
        }
    }
}
