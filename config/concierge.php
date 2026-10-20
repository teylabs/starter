<?php

use App\Models\User;
use Tey\Kit\Features\Workspaces\Models\Workspace;

return ['mode' => 'workspaces', 'workspaces' => ['engine' => 'concierge'], 'features' => ['membership' => true, 'tenant_invitations' => true], 'models' => ['user' => User::class, 'workspace' => Workspace::class], 'guard' => 'web', 'roles' => ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'viewer' => ['label' => 'Viewer', 'grantable_by' => ['owner']]], 'managers' => ['owner'], 'registration' => ['mode' => 'closed', 'domains' => []], 'local_login' => ['enabled' => env('CONCIERGE_LOCAL_LOGIN', false) === true, 'account' => ['email' => env('CONCIERGE_LOCAL_LOGIN_EMAIL', 'owner@example.com')]], 'invitations' => ['deliver' => false], 'tenant_invitations' => ['platform_owners' => []], 'owners' => array_values(array_filter(array_map('trim', explode(',', (string) env('CONCIERGE_OWNERS', ''))), fn (string $email) => $email !== ''))];
