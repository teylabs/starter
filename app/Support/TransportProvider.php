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
        Passport::tokensCan(app(StarterRegistry::class)->tokenScopes());
        Passport::tokensExpireIn(now()->addHour());
        Passport::authorizationView('auth.authorize');
        Fortify::loginView(fn () => Inertia::render('Auth/Login', ['title' => 'Sign in']));
    }
}
