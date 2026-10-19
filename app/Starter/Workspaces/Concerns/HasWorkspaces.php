<?php

namespace App\Starter\Workspaces\Concerns;

use Spatie\Permission\Traits\HasRoles;
use Tey\Concierge\Concerns\InteractsWithMemberships;

/** Workspace membership and team-scoped roles for the host's user model; the model still implements Concierge's `Member`. */
trait HasWorkspaces
{
    use HasRoles, InteractsWithMemberships;

    protected $guard_name = 'web';
}
