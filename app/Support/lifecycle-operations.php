<?php

use App\Models\Workspace;
use App\Support\TenantBoundary;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;
use Tey\Concierge\Concierge;

// Host operation and tenant acceptance for `starter:maintain`. Read-only; anything unproven returns false.
return static function (): bool {
    if (Workspace::current() !== null || app(Concierge::class)->context()->current() !== null) {
        return false;
    }
    try {
        foreach (['users', 'workspaces', 'concierge_memberships', 'settings', 'notes', 'audit_events'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }
        $refused = false;
        try {
            app(TenantBoundary::class)->run((int) DB::table('workspaces')->max('id') + 1, fn () => true);
        } catch (ModelNotFoundException) {
            $refused = true;
        }
        $defined = Feature::defined();
        foreach (array_keys(config('foundation.capabilities', [])) as $capability) {
            if (! in_array($capability, $defined, true)) {
                return false;
            }
        }

        return $refused && Gate::has('concierge.manage-tenants');
    } finally {
        if (Workspace::current() !== null) {
            Workspace::forgetCurrent();
        }
    }
};
