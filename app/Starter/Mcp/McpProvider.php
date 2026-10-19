<?php

namespace App\Starter\Mcp;

use App\Starter\Core\StarterRegistry;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

final class McpProvider extends ServiceProvider
{
    public function boot(): void
    {
        Passport::tokensCan(app(StarterRegistry::class)->tokenScopes());
        Passport::tokensExpireIn(now()->addHour());
        Passport::authorizationView('auth.authorize');
    }
}
