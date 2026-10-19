<?php

namespace App\Starter\Workspaces\Operations;

use App\Starter\Core\StarterRegistry;
use App\Starter\Workspaces\Models\Workspace;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Models\TenantInvitation;

final class ProvisionWorkspace
{
    // Host callback inside Concierge's token-validated acceptance transaction.
    public function handle(Member $owner, TenantInvitation $invitation): Workspace
    {
        $workspace = Workspace::create(['name' => $owner->conciergeDisplayName(), 'admitted' => true, 'provisioning_invitation_id' => $invitation->id]);
        $workspace->execute(function () use ($workspace): void {
            foreach (app(StarterRegistry::class)->provisioners() as $provision) {
                $provision($workspace);
            }
        });

        return $workspace;
    }
}
