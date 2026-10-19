<?php

namespace App\Starter\Mcp\Http;

use App\Starter\Access\Access;
use App\Starter\Core\Principal;
use App\Starter\Mcp\Operations\WorkspaceConnection;
use App\Starter\Workspaces\TenantBoundary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Token;

final class ConnectionController
{
    public function store(Request $request, int $workspace): mixed
    {
        $result = app(TenantBoundary::class)->run($workspace, fn () => app(WorkspaceConnection::class)->approve(new Principal((int) $request->user()->getKey()), $request->except(['_token'])));

        return $request->expectsJson() ? response()->json($result, $result['created'] ? 201 : 200) : redirect('/workspaces/'.$workspace.'/connections');
    }

    public function index(Request $request, int $workspace): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace) {
            app(Access::class)->authorize(new Principal((int) $request->user()->getKey()), 'settings.read');
            $tokens = Token::query()->where('user_id', $request->user()->getKey())->where('revoked', false)->where('expires_at', '>', now())->get()->filter(fn ($token) => $token->can('mcp:use') && $token->can('settings.read') && $token->client && ! $token->client->revoked && $token->client->hasGrantType('authorization_code'));
            $connections = DB::table('connections')->where('workspace_id', $workspace)->where('actor_id', $request->user()->getKey())->whereNotNull('oauth_token_id')->get();

            return view('connections', compact('workspace', 'tokens', 'connections'));
        });
    }

    public function destroy(Request $request, int $workspace, int $connection): mixed
    {
        $result = app(TenantBoundary::class)->run($workspace, fn () => app(WorkspaceConnection::class)->revoke(new Principal((int) $request->user()->getKey()), $connection));

        return $request->expectsJson() ? response()->json($result) : redirect('/workspaces/'.$workspace.'/connections');
    }
}
