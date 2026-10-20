<?php

namespace App\Providers;

use App\Kit\KitHost;
use Illuminate\Support\ServiceProvider;
use Tey\Kit\LifecycleHost;

/** Binds this app's trusted kit host. Written by kit:install; the app owns it. */
final class KitHostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LifecycleHost::class, fn () => new KitHost);
    }
}
