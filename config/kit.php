<?php

// tey/kit's defaults. Publish them with `php artisan vendor:publish --tag=kit-config`;
// the published config/kit.php is the app's own file, and kit updates never touch it.
return [
    // The adopted features: the kit registers exactly these (since 0.18; unset or empty registers none). Each one's pages are
    // the app's own, written once by `kit:scaffold <feature>` (docs/lifecycle.md).
    'features' => ['core', 'workspaces', 'access', 'settings', 'shell', 'session-ui', 'workspace-ui', 'mcp'],

    // The host's user model. Null uses the `users` auth provider's model.
    'models' => ['user' => null],

    // Workspace capabilities that are switched on.
    'capabilities' => ['workspace.dashboard' => true, 'workspace.settings' => true, 'workspace.search' => true],

    // Per-feature route options: enabled, prefix, middleware, name (a route-name prefix).
    'routes' => [],

    // A route name taking `workspace`, where an accepted invitation lands. Null uses the first navigation link.
    'invitation_redirect' => null,

    'mcp' => ['connection_ability' => 'settings.read'],

    // Row-level security is on by default on PostgreSQL (tey/kit docs/rls.md): every table with a workspace_id is protected unless listed here with its
    // reason. The kit's own list first (Concierge's membership tables and the permission tables), then: audit_events keeps rows with no workspace, user_state
    // uses workspace 0, workspace_mail_identities is read by invitation mail before a workspace is entered, connections is read at OAuth consent, and
    // mcp_runs is read before a workspace is entered. Set KIT_RLS=false only for a database that can't run it.
    'tenancy' => [
        'rls' => [
            'enabled' => filter_var(env('KIT_RLS', env('DB_CONNECTION') === 'pgsql'), FILTER_VALIDATE_BOOL),
            'connection' => env('KIT_RLS_CONNECTION'),
            'admin_connection' => env('KIT_RLS_ADMIN_CONNECTION', 'pgsql_admin'),
            'except' => [
                'concierge_memberships', 'concierge_membership_scopes', 'concierge_ownerships', 'concierge_invitations', 'concierge_tenant_invitations',
                'roles', 'model_has_roles', 'model_has_permissions',
                'audit_events', 'user_state', 'workspace_mail_identities', 'connections', 'mcp_runs',
            ],
        ],
    ],
];
