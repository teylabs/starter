<?php

namespace App\Starter\Workspaces;

use App\Models\User;
use App\Starter\Workspaces\Operations\ProvisionWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Tey\Concierge\Concierge;

final class WorkspacesProvider extends ServiceProvider
{
    public function boot(): void
    {
        app(Concierge::class)->provisionWorkspaceUsing(fn ($owner, $invitation) => app(ProvisionWorkspace::class)->handle($owner, $invitation));
        Gate::define('concierge.manage-tenants', fn (User $user): bool => $user->conciergeHasVerifiedEmail() && app(Concierge::class)->account($user)->isActive() && DB::table('operators')->where('actor_id', $user->id)->exists());
    }
}
