<?php

namespace App\Starter\SessionUi;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

final class SessionUiProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::loginView(fn () => Inertia::render('Auth/Login', ['title' => 'Sign in']));
    }
}
