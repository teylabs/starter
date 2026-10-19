<?php

// tey/kit's defaults. Publish them with `php artisan vendor:publish --tag=kit-config`;
// the published config/kit.php is the app's own file, and kit updates never touch it.
return [
    // The adopted features. The template ships every feature's files, but no installed record, which is bound to a
    // checkout's own path. Null reads them from that record once `kit:add` has claimed the files (docs/lifecycle.md).
    'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'mcp', 'notes'],

    // The host's user model. Null uses the `users` auth provider's model.
    'models' => ['user' => null],

    // Workspace capabilities that are switched on.
    'capabilities' => ['workspace.settings' => true, 'workspace.notes' => true],

    // Per-feature route options: enabled, prefix, middleware, name (a route-name prefix).
    'routes' => [],

    // A route name taking `workspace`, where an accepted invitation lands. Null uses the first navigation link.
    'invitation_redirect' => null,

    'mcp' => ['connection_ability' => 'settings.read'],
];
