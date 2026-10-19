<?php

namespace App\Starter\Mcp;

use App\Models\User;
use App\Starter\Core\Principal;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;
use RuntimeException;

final class Context
{
    public static function principal(string $scope): Principal
    {
        $user = request()->user();
        $principal = request()->attributes->get('mcp.principal');
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        $fresh = $token instanceof AccessToken ? Token::query()->whereKey($token->oauth_access_token_id)->where('user_id', $user->getKey())->where('client_id', $token->oauth_client_id)->where('revoked', false)->where('expires_at', '>', now())->first() : null;
        if (! $principal instanceof Principal || $principal->actorId !== (int) $user?->getKey() || ! $token?->can($scope) || ! $fresh?->can('mcp:use') || ! $fresh->can($scope) || ! $fresh->client || $fresh->client->revoked) {
            throw new RuntimeException('connection_denied');
        }

        if (! DB::table('connections')->where('id', $principal->connectionId)->where('actor_id', $principal->actorId)->where('oauth_token_id', $fresh->id)->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
            throw new RuntimeException('connection_denied');
        }

        return $principal;
    }
}
