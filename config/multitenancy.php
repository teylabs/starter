<?php

use Tey\Concierge\Jobs\DeliverInvitation;
use Tey\Concierge\Jobs\DeliverTenantInvitation;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Tasks\ConciergeContextTask;
use Tey\Kit\Features\Workspaces\Tasks\FlushFeatureCacheTask;

// Queues are tenant-aware only by opt-in (tey/kit 0.38): a queued job, notification, mailable or listener that needs its workspace declares spatie's TenantAware
// (`make:job` and the other make stubs in stubs/ start that way); one that runs centrally declares NotTenantAware.
return ['tenant_finder' => null,
    'tenant_aware_jobs' => [DeliverInvitation::class], 'not_tenant_aware_jobs' => [DeliverTenantInvitation::class], 'tenant_model' => Workspace::class, 'switch_tenant_tasks' => [ConciergeContextTask::class, FlushFeatureCacheTask::class], 'queues_are_tenant_aware_by_default' => false, 'tenant_database_connection_name' => null, 'landlord_database_connection_name' => null, 'current_tenant_context_key' => 'tenantId', 'current_tenant_container_key' => 'currentTenant'];
