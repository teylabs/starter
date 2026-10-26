<?php

require_once __DIR__.'/../Concerns/FreshClone.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\FreshClone;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Concierge\Support\ConciergeModels;

// The account pages in a real browser: the two-factor setup flow (password, QR code and key, a code from the authenticator, the recovery codes shown once,
// then turned off again) and the signed-in devices section. Listing devices needs the database session driver, which the harness does not use, so it says so.
it('sets two-factor authentication up with a code and shows the devices section', function (): void {
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        config(['concierge.local_login' => FreshClone::localLogin('local')]);
        app()->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
        app('router')->getRoutes()->refreshNameLookups();
        expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
        expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);
        $user = ConciergeModels::user()::query()->where('email', config('concierge.local_login.account.email'))->firstOrFail();
        $user->forceFill(['password' => Hash::make('account-test-pass-1')])->save();

        $page = visit('/login')->click('[data-local-login] button')->assertPathIs('/');
        $page->navigate('/me/security')->assertSee('Two-factor authentication')->assertSee('Security log')->assertNoJavaScriptErrors();
        // The devices section says why it cannot list devices here.
        $page->assertPresent('[data-sessions-unsupported]')->assertSee('SESSION_DRIVER=database');
        // The sign-in is in the log.
        $page->assertPresent('[data-security-log] [data-event="sign_in"]');

        // Start: the password, then the QR code and the key.
        $page->type('#two-factor-password', 'account-test-pass-1')->click('Turn on')->assertPresent('[data-two-factor-qr] svg')->assertPresent('[data-two-factor-key]');
        $key = (string) $page->script('() => document.querySelector("[data-two-factor-key]").textContent.trim()');
        expect($key)->not->toBe('');
        // A wrong code does not turn it on.
        $page->type('#two-factor-code', '000000')->click('Confirm and turn on')->assertSee('invalid');
        // The right one does, and the recovery codes are shown, once.
        $page->type('#two-factor-code', app(Google2FA::class)->getCurrentOtp($key))->click('Confirm and turn on');
        $page->assertSee('Two-factor authentication is on')->assertCount('[data-recovery-codes] li', 8)->assertNoJavaScriptErrors();
        settled($page)->screenshot(filename: 'account-two-factor');
        $page->navigate('/me/security')->assertMissing('[data-recovery-codes]')->assertSee('8 recovery codes left');

        // Turning it off asks for the password and confirms first.
        $page->type('#off-password', 'account-test-pass-1')->click('Turn off')->assertPresent('[data-confirm-dialog]');
        $page->click('[data-confirm-action]')->assertSee('Add a second step to signing in')->assertNoJavaScriptErrors();
        expect($user->fresh()->two_factor_secret)->toBeNull();

        // Sibling sections keep one persistent sublayout: a sentinel on the sidebar's frame survives moving between them (a client visit, no reload) while the current item,
        // the heading and the trail change; back and forward restore them.
        $page->navigate('/me/security')->assertPresent('[data-settings-frame]');
        $page->script('() => { document.querySelector("[data-settings-frame]").dataset.sentinel = "kept"; return true; }');
        $state = fn (): array => $page->script('() => [document.querySelector("[data-settings-frame]").dataset.sentinel ?? null, document.querySelector("[data-settings-sidebar] [aria-current=page]")?.textContent.trim(), document.querySelector("[data-slot=page-heading]")?.textContent.trim(), location.pathname, [...document.querySelectorAll("[data-shell-breadcrumbs] li")].at(-1)?.textContent.trim()]');
        expect($state())->toBe(['kept', 'Security', 'Security', '/me/security', 'Security']);
        $page->click('[data-settings-sidebar] a[href="/me/password"]')->assertPathIs('/me/password');
        expect($state())->toBe(['kept', 'Password', 'Password', '/me/password', 'Password']);
        $page->click('[data-settings-sidebar] a[href="/me/profile"]')->assertPathIs('/me/profile');
        expect($state())->toBe(['kept', 'Profile', 'Profile', '/me/profile', 'Profile']);
        $page->script('() => { history.back(); return true; }');
        $page->assertPathIs('/me/password');
        expect($state())->toBe(['kept', 'Password', 'Password', '/me/password', 'Password']);
        $page->script('() => { history.forward(); return true; }');
        $page->assertPathIs('/me/profile');
        expect($state())->toBe(['kept', 'Profile', 'Profile', '/me/profile', 'Profile']);
        // Leaving the section for one without a sidebar drops the sublayout and the item does not linger.
        $page->navigate('/')->assertMissing('[data-settings-sidebar]');

        // Password page and profile page render on the same pieces.
        $page->navigate('/me/password')->assertPresent('[data-form-card] [data-form-actions]')->assertSee('Update password')->assertNoJavaScriptErrors();
        $page->navigate('/me/profile')->assertPresent('#profile-email')->assertNoJavaScriptErrors();
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
