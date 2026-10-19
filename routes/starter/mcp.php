<?php

use App\Starter\Mcp\Http\Middleware\BindMcpConnection;
use App\Starter\Mcp\WorkspaceServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes(registration: false);
Mcp::web('/mcp/connections/{connection}', WorkspaceServer::class)->whereNumber('connection')->middleware(['auth:api', 'concierge.active', BindMcpConnection::class]);

// Public Laravel route middleware composes the package's web/session routes.
foreach (['passport.authorizations.authorize', 'passport.authorizations.approve', 'passport.authorizations.deny'] as $name) {
    $route = Route::getRoutes()->getByName($name);
    if ($route === null) {
        throw new RuntimeException('Passport authorization routes are unavailable.');
    }
    $route->middleware(['auth:web', 'concierge.active']);
}
