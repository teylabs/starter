<?php

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Fonts;

// Smoke, from zero: a local boot with .env.example's local sign-in settings and `starter:first-owner`'s defaults. The
// sign-in page offers one-click sign-in as the owner; the click reaches the app shell, which lists their workspace and
// opens it, and their name is in the user menu, not the nav.
// Breadcrumbs are not asserted here: gretel shares them when the app boots, and the browser plugin flushes Inertia's shared props between
// requests, so they never reach this harness's pages. tests/BreadcrumbsTest.php covers the trails over HTTP.
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
            ->assertPathIs('/dashboard')
            ->assertPresent('[data-shell-bar]')
            ->assertNoJavaScriptErrors();
        settled($page)->screenshot(filename: 'app-shell');

        // The light/dark toggle: named, pressed when dark, moves off System on a click, and the keyboard works. A reload keeps it
        // (the head script sets the class before paint).
        $dark = fn (): bool => (bool) $page->script('() => document.documentElement.classList.contains("dark")');
        $attribute = fn (string $name): ?string => $page->script('() => document.querySelector("[data-appearance-toggle]").getAttribute("'.$name.'")');
        $page->script('() => localStorage.removeItem("appearance")');
        $page->refresh()->assertPresent('[data-appearance-toggle]');
        expect($attribute('aria-label'))->toBe('Dark mode');
        expect($dark())->toBeFalse();
        expect($attribute('aria-pressed'))->toBe('false');
        $page->click('[data-appearance-toggle]');
        expect($dark())->toBeTrue();
        expect($attribute('aria-pressed'))->toBe('true');
        expect($page->script('() => localStorage.getItem("appearance")'))->toBe('dark');
        $page->refresh()->assertPresent('[data-appearance-toggle]');
        expect($dark())->toBeTrue();
        expect($attribute('aria-pressed'))->toBe('true');
        $page->keys('[data-appearance-toggle]', 'Enter');
        expect($dark())->toBeFalse();
        expect($attribute('aria-pressed'))->toBe('false');
        expect($page->script('() => localStorage.getItem("appearance")'))->toBe('light');
        $page->script('() => localStorage.removeItem("appearance")');
        $page->refresh()->assertPresent('[data-shell-bar]');
        // On a phone the same control is in the drawer.
        $page->resize(390, 844)->click('[data-shell-menu-button]')->assertPresent('[data-shell-sheet] [data-appearance-toggle]');
        $page->keys('[data-shell-sheet]', 'Escape')->resize(1440, 900);

        $page->click('[data-shell-user-menu]')
            ->assertSee('Local owner')
            ->assertSee('owner@example.com');
        settled($page)->screenshot(filename: 'user-menu');

        $page->keys('[role=menu]', 'Escape')
            ->assertSeeIn('[data-shell-nav]', 'Dashboard')
            ->click('[data-shell-nav] a[href="/settings"]')
            ->assertPathIs('/settings')
            ->assertSee('Delegated access for MCP clients')
            ->click('main a[href="/settings/general"]')
            ->assertPathIs('/settings/general')
            ->assertSeeIn('[data-shell-nav]', 'Dashboard')
            ->assertNoJavaScriptErrors();
        // The shell owns the vertical rhythm: the breadcrumb row sits about 16 to 24px below the header's border, in light and dark and on a phone.
        $gap = fn (): int => (int) $page->script('() => Math.round(document.querySelector("[data-shell-breadcrumbs]").getBoundingClientRect().top - document.querySelector("[data-shell-bar]").getBoundingClientRect().bottom)');
        expect($gap())->toBeGreaterThanOrEqual(14)->toBeLessThanOrEqual(24);
        $page->script('() => document.documentElement.classList.add("dark")');
        expect($gap())->toBeGreaterThanOrEqual(14)->toBeLessThanOrEqual(24);
        $page->script('() => document.documentElement.classList.remove("dark")');
        $page->resize(390, 844);
        expect($gap())->toBeGreaterThanOrEqual(14)->toBeLessThanOrEqual(24);
        $page->resize(1440, 900);
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
