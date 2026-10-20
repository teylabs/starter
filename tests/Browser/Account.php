<?php

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Concierge\Support\ConciergeModels;

// The account pages in a real browser: the two-factor setup flow (password, QR code and key, a code from the authenticator, the recovery codes shown once,
// then turned off again) and the signed-in devices section. Listing devices needs the database session driver, which the harness does not use, so it says so.
it('sets two-factor authentication up with a code and shows the devices section', function (): void {
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        $example = Dotenv::parse((string) file_get_contents(base_path('.env.example')));
        config(['concierge.local_login.enabled' => ($example['CONCIERGE_LOCAL_LOGIN'] ?? null) === 'true', 'concierge.local_login.account.email' => $example['CONCIERGE_LOCAL_LOGIN_EMAIL'] ?? null]);
        app()->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
        app('router')->getRoutes()->refreshNameLookups();
        expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
        expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);
        $user = ConciergeModels::user()::query()->where('email', $example['CONCIERGE_LOCAL_LOGIN_EMAIL'])->firstOrFail();
        $user->forceFill(['password' => Hash::make('account-test-pass-1')])->save();

        $page = visit('/login')->click('[data-local-login] button')->assertPathIs('/dashboard');
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

        // Password page and profile page render on the same pieces.
        $page->navigate('/me/password')->assertPresent('[data-form-card] [data-form-actions]')->assertSee('Update password')->assertNoJavaScriptErrors();
        $page->navigate('/me/profile')->assertPresent('#profile-email')->assertNoJavaScriptErrors();
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
