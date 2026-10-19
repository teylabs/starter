<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Access;
use App\Support\Principal;
use App\Support\TenantBoundary;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;

final class BindMcpConnection
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        abort_unless($request->bearerToken() !== null && $token instanceof AccessToken, 401);
        $fresh = Token::query()->whereKey($token->oauth_access_token_id)->where('user_id', $user->id)->where('revoked', false)->where('expires_at', '>', now())->first();
        abort_unless($fresh && $token->can('mcp:use') && $fresh->client_id === $token->oauth_client_id && $fresh->can('mcp:use') && $fresh->client && ! $fresh->client->revoked && $fresh->client->hasGrantType('authorization_code'), 403);
        $origin = $request->header('Origin');
        abort_if($origin !== null && $origin !== rtrim(config('app.url'), '/'), 403);
        $connection = DB::table('connections')->where('id', $request->route('connection'))->where('actor_id', $user->id)->where('oauth_token_id', $fresh->id)->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_unless($connection, 403);
        $principal = new Principal((int) $user->id, (int) $connection->id);
        $request->attributes->set('mcp.principal', $principal);

        return app(TenantBoundary::class)->run((int) $connection->workspace_id, function () use ($principal, $request, $next) {
            app(Access::class)->authorize($principal, 'settings.read');

            return $next($request);
        });
    }
}
