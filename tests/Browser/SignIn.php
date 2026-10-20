<?php

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Fonts;

// Smoke, from zero: a local boot with .env.example's local sign-in settings and `starter:first-owner`'s defaults. The
// sign-in page offers one-click sign-in as the owner; the click reaches the app shell, which lists their workspace and
// opens it, and their name is in the user menu, not the nav.
// The web fonts have arrived (or failed) before each screenshot.
function settled(mixed $page): mixed
{
    $page->script('() => document.fonts.ready.then(() => true)');

    return $page;
}

it('signs the first owner in with one click and reaches the app shell', function (): void {
    $environment = getenv('APP_ENV');
    try {
        // A local boot, the way Concierge's provider registers its route for one; Concierge decides the rest.
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        $example = Dotenv::parse((string) file_get_contents(base_path('.env.example')));
        config(['concierge.local_login.enabled' => ($example['CONCIERGE_LOCAL_LOGIN'] ?? null) === 'true', 'concierge.local_login.account.email' => $example['CONCIERGE_LOCAL_LOGIN_EMAIL'] ?? null]);
        app()->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
        app('router')->getRoutes()->refreshNameLookups();
        expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
        expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);
        $workspace = Workspace::query()->sole()->id;

        $page = visit('/login')
            ->assertSee('Sign in to your account')
            ->assertPresent('[data-guest-card]')
            ->assertSeeIn('[data-local-login]', 'Sign in as owner@example.com')
            ->assertNoJavaScriptErrors();
        settled($page)->screenshot(filename: 'sign-in');

        $page->click('[data-local-login] button')
            ->assertPathIs("/workspaces/{$workspace}/notes")
            ->assertPresent('[data-shell-bar]')
            ->assertNoJavaScriptErrors();
        settled($page)->screenshot(filename: 'app-shell');

        $page->click('[data-shell-user-menu]')
            ->assertSee('Local owner')
            ->assertSee('owner@example.com');
        settled($page)->screenshot(filename: 'user-menu');

        $page->keys('[role=menu]', 'Escape')
            ->assertSeeIn('[data-shell-nav]', 'Notes')
            ->click('[data-shell-settings]')
            ->click('Workspace settings')
            ->assertPathIs("/workspaces/{$workspace}/settings")
            ->assertSeeIn('[data-shell-nav]', 'Notes')
            ->assertNoJavaScriptErrors();
        settled($page)->screenshot(filename: 'workspace');

        // Fonts come from the kit's set (KIT_FONTS): the text face on the body, the display face and weight on the page title.
        // Checked for the active set, then for another, so the test holds whichever set the app uses.
        $family = fn (string $stack): string => trim(explode(',', $stack)[0], " '\"");
        $faces = function () use ($page, $family): array {
            [$body, $title, $weight] = $page->script('() => { const h1 = document.querySelector("[data-shell-bar] ~ * h1, main h1"); return [getComputedStyle(document.body).fontFamily, getComputedStyle(h1).fontFamily, getComputedStyle(h1).fontWeight]; }');

            return [$family($body), $family($title), $weight];
        };
        $expected = fn (string $name): array => [$family(Fonts::set($name)['sans']), $family(Fonts::set($name)['serif']), (string) Fonts::set($name)['display_weight']];
        $active = config('kit.fonts');
        expect($faces())->toBe($expected($active));
        $other = $active === 'instrument' ? 'instrument-inter' : 'instrument';
        config(['kit.fonts' => $other]);
        $page->refresh();
        expect($faces())->toBe($expected($other));
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
