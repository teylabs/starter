<?php

namespace App\Support;

use App\Models\User;
use App\Models\Workspace;
use App\Operations\ProvisionWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Tey\Concierge\Concierge;

final class FoundationProvider extends ServiceProvider
{
    public function boot(): void
    {
        app(Concierge::class)->provisionWorkspaceUsing(fn ($owner, $invitation) => app(ProvisionWorkspace::class)->handle($owner, $invitation));
        Gate::define('concierge.manage-tenants', fn (User $user): bool => $user->conciergeHasVerifiedEmail() && app(Concierge::class)->account($user)->isActive() && DB::table('operators')->where('actor_id', $user->id)->exists());
        foreach (['workspace.settings', 'workspace.notes'] as $capability) {
            Feature::define($capability, fn (Workspace $workspace): bool => (bool) (config('foundation.capabilities', [])[$capability] ?? false));
        }
    }
}
