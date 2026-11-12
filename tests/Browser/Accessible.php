<?php

require_once __DIR__.'/../Concerns/FreshClone.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\FreshClone;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Concierge\Support\ConciergeModels;

// axe's WCAG 2.2 AA scan (assertAccessible) on the signed-in dashboard and the People settings page, and on an invitation link
// that matches no invitation, which still says what it is in its one h1.
function accessibleSignIn(): mixed
{
    config(['concierge.local_login' => FreshClone::localLogin('local')]);
    app()->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
    app('router')->getRoutes()->refreshNameLookups();
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
    expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);
    ConciergeModels::user()::query()->where('email', config('concierge.local_login.account.email'))->firstOrFail()->forceFill(['password' => Hash::make('accessible-test-pass-1')])->save();

    return visit('/login')->click('[data-local-login] button')->assertPathIs('/');
}

it('passes axe on the dashboard and the People settings page', function (): void {
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        $page = accessibleSignIn()->assertNoJavaScriptErrors();
        assertAccessible($page, 'the dashboard');

        $page->navigate('/settings/people')->assertSee('Members')->assertPresent('[data-data-table]')->assertNoJavaScriptErrors();
        assertAccessible($page, 'the People settings page');
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});

// An unknown token is the invitation page's "not valid" state; a token the route cannot match (here one with a dot) is the guest 404
// error page, outside the shell, which names itself in its own h1 since the shell's Page heading is not drawn there.
it('heads an unknown invitation link with one h1 and passes axe', function (string $token, string $heading): void {
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
    $page = visit('/invitations/'.$token)->assertSee($heading)->assertNoJavaScriptErrors();
    expect((int) $page->script('() => document.querySelectorAll("h1").length'))->toBe(1);
    expect(trim((string) $page->script('() => document.querySelector("h1")?.textContent ?? ""')))->toBe($heading);
    assertAccessible($page, 'an unknown invitation link');
})->with([
    'no such invitation' => ['no-such-invitation-token', 'This link is not valid'],
    'a token the route cannot match' => ['not.a.token', 'Page not found'],
]);
