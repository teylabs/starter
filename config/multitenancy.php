<?php

use Tey\Concierge\Jobs\DeliverTenantInvitation;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Tasks\ConciergeContextTask;
use Tey\Kit\Features\Workspaces\Tasks\FlushFeatureCacheTask;

return ['tenant_finder' => null,
    'not_tenant_aware_jobs' => [DeliverTenantInvitation::class], 'tenant_model' => Workspace::class, 'switch_tenant_tasks' => [ConciergeContextTask::class, FlushFeatureCacheTask::class], 'queues_are_tenant_aware_by_default' => true, 'tenant_database_connection_name' => null, 'landlord_database_connection_name' => null, 'current_tenant_context_key' => 'tenantId', 'current_tenant_container_key' => 'currentTenant'];
