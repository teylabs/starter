<?php

namespace App\Support;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Laravel\Passport\Passport;

final class TransportProvider extends ServiceProvider
{
    public function boot(): void
    {
        Passport::tokensCan(['mcp:use' => 'Use workspace MCP', 'settings.read' => 'Read workspace settings', 'settings.write' => 'Update workspace settings', 'notes.read' => 'Read workspace notes', 'notes.write' => 'Create, update and delete workspace notes']);
        Passport::tokensExpireIn(now()->addHour());
        Passport::authorizationView('auth.authorize');
        Fortify::loginView(fn () => Inertia::render('Auth/Login', ['title' => 'Sign in']));
    }
}
