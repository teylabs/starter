<?php

namespace App\Operations;

use App\Models\Workspace;
use App\Settings\WorkspaceSettings;
use Spatie\LaravelSettings\Factories\SettingsRepositoryFactory;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Models\TenantInvitation;

final class ProvisionWorkspace
{
    // Host callback inside Concierge's token-validated acceptance transaction.
    public function handle(Member $owner, TenantInvitation $invitation): Workspace
    {
        $workspace = Workspace::create(['name' => $owner->conciergeDisplayName(), 'admitted' => true, 'provisioning_invitation_id' => $invitation->id]);
        $workspace->execute(function () use ($workspace): void {
            $repository = SettingsRepositoryFactory::create();
            $repository->createProperty(WorkspaceSettings::group(), 'display_name', $workspace->name);
            $repository->createProperty(WorkspaceSettings::group(), 'timezone', 'UTC');
        });

        return $workspace;
    }
}
