<?php

namespace App\Starter\Access;

use App\Starter\Core\StarterRegistry;
use App\Starter\Workspaces\Models\Workspace;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

final class AccessProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (app(StarterRegistry::class)->workspaceCapabilities() as $capability) {
            Feature::define($capability, fn (Workspace $workspace): bool => (bool) (config('foundation.capabilities', [])[$capability] ?? false));
        }
    }
}
