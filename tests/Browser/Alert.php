<?php

require_once __DIR__.'/../Concerns/FreshClone.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\FreshClone;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;

// The kit Alert on the surfaces that use it: an inline callout under the page's content, announced politely because it appears after something the person did, not
// dismissible (nothing to ignore), and readable (4.5:1) in light and dark, at phone and desktop width.
it('shows the account notice as a polite, readable, undismissable alert', function (): void {
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        config(['concierge.local_login' => FreshClone::localLogin('local')]);
        app()->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
        app('router')->getRoutes()->refreshNameLookups();
        expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
        expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);
        Route::middleware('web')->get('/_flash-notice', fn () => redirect('/me/password')->with('account_notice', 'Your password was changed.'));

        $page = visit('/login')->click('[data-local-login] button')->assertPathIs('/');
        $page->navigate('/_flash-notice')->assertPathIs('/me/password')
            ->assertPresent('[data-account-notice][data-slot="alert"][data-variant="info"][data-tier="pale"][role="status"]')
            ->assertSeeIn('[data-account-notice]', 'Your password was changed.')
            ->assertMissing('[data-account-notice] [data-slot="alert-dismiss"]')
            ->assertNoJavaScriptErrors();
        $contrast = '() => { const ctx = document.createElement("canvas").getContext("2d", { willReadFrequently: true }); const norm = (c) => { ctx.clearRect(0, 0, 1, 1); ctx.fillStyle = c; ctx.fillRect(0, 0, 1, 1); const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data; return [r, g, b, a / 255]; }; const lum = ([r, g, b]) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); }; const el = document.querySelector("[data-account-notice]"); const s = getComputedStyle(el); const page = norm(getComputedStyle(document.body).backgroundColor); const bg = norm(s.backgroundColor); const over = [0, 1, 2].map((i) => bg[i] * bg[3] + page[i] * (1 - bg[3])); const text = norm(s.color); const a = lum(over) + 0.05; const b = lum(text) + 0.05; return Math.max(a, b) / Math.min(a, b); }';
        foreach ([[1440, 900], [390, 844]] as [$width, $height]) {
            foreach (['light', 'dark'] as $theme) {
                $page->resize($width, $height)->script('() => document.documentElement.classList.toggle("dark", '.($theme === 'dark' ? 'true' : 'false').')');
                expect($page->script($contrast))->toBeGreaterThanOrEqual(4.5);
                expect($page->script('() => document.documentElement.scrollWidth - document.documentElement.clientWidth'))->toBeLessThanOrEqual(0);
            }
        }
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
