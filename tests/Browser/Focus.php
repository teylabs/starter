<?php

require_once __DIR__.'/../Concerns/FreshClone.php';

use Illuminate\Contracts\Console\Kernel;
use Tests\Concerns\FreshClone;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;

// The FocusShell in a real browser: a signed-in page outside a workspace has the product on the left and the account cluster on the right, and no app-shell chrome;
// it fits at phone and desktop width in both themes.
it('renders a signed-in page outside a workspace in the FocusShell at phone and desktop width, light and dark', function (): void {
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        config(['concierge.local_login' => FreshClone::localLogin('local')]);
        app()->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
        app('router')->getRoutes()->refreshNameLookups();
        expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
        expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);

        $page = visit('/login')->click('[data-local-login] button')->assertPathIs('/dashboard');
        $page->navigate('/invitations/'.str_repeat('a', 40))
            ->assertPresent('[data-focus-shell] [data-focus-header] [data-focus-product]')
            ->assertPresent('[data-focus-account] [data-focus-switch]')
            ->assertPresent('[data-focus-account] [data-focus-sign-out]')
            ->assertMissing('[data-shell-bar]')
            ->assertMissing('[data-shell-breadcrumbs]')
            ->assertMissing('[data-focus-step]')
            ->assertNoJavaScriptErrors();
        foreach ([[1440, 900], [390, 844]] as [$width, $height]) {
            foreach (['light', 'dark'] as $theme) {
                $page->resize($width, $height)->script('(theme) => document.documentElement.classList.toggle("dark", theme === "dark")', $theme);
                $overflow = $page->script('() => document.documentElement.scrollWidth - document.documentElement.clientWidth');
                expect($overflow)->toBeLessThanOrEqual(0);
                // The account cluster stays inside the viewport.
                $right = $page->script('() => document.querySelector("[data-focus-account]").getBoundingClientRect().right - document.documentElement.clientWidth');
                expect($right)->toBeLessThanOrEqual(0);
            }
        }
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
