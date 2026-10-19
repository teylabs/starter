<?php

namespace App\Adapters;

use App\Operations\WorkspaceProfile;
use App\Support\Principal;
use App\Support\TenantBoundary;

final class SettingsUi
{
    public function show(Principal $principal, int $workspaceId): array
    {
        return app(TenantBoundary::class)->run($workspaceId, fn () => app(WorkspaceProfile::class)->read($principal)->toArray());
    }

    public function update(Principal $principal, int $workspaceId, array $input): array
    {
        return app(TenantBoundary::class)->run($workspaceId, fn () => app(WorkspaceProfile::class)->update($principal, $input)->toArray());
    }
}
