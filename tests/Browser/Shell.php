<?php

require_once __DIR__.'/../Concerns/FreshClone.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\FreshClone;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Actions\InviteTenant;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Operations\AcceptWorkspaceInvitation;

// The shell in a real browser: the breadcrumb sub-bar as its own translucent surface (distinct from the header and the page, readable over black and white content, light and
// dark), controls that read on a dark --shell-bar, the workspace name always in the bar (a switcher with two), and very long names and wide marks that never push the controls out.

/** Signs the local owner in and returns the page. */
function shellSignIn(): mixed
{
    config(['concierge.local_login' => FreshClone::localLogin('local')]);
    app()->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
    app('router')->getRoutes()->refreshNameLookups();
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
    expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);
    ConciergeModels::user()::query()->where('email', config('concierge.local_login.account.email'))->firstOrFail()->forceFill(['password' => Hash::make('shell-test-pass-1')])->save();

    return visit('/login')->click('[data-local-login] button')->assertPathIs('/');
}

/** The page script that normalises any CSS colour to [r, g, b, a] through a canvas, then runs `$body` with `norm` and `$` in scope. */
function shellScript(string $body): string
{
    return '() => { const norm = (c) => { const x = document.createElement("canvas").getContext("2d", { willReadFrequently: true }); x.clearRect(0, 0, 1, 1); x.fillStyle = c; x.fillRect(0, 0, 1, 1); const d = x.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2], d[3] / 255]; }; const $ = (s) => document.querySelector(s); '.$body.' }';
}

function shellLuminance(array $rgb): float
{
    $channel = fn (float $value): float => ($value /= 255) <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;

    return 0.2126 * $channel($rgb[0]) + 0.7152 * $channel($rgb[1]) + 0.0722 * $channel($rgb[2]);
}

/** Composites a colour with alpha over an opaque one. */
function shellOver(array $top, array $under): array
{
    return [$top[0] * $top[3] + $under[0] * (1 - $top[3]), $top[1] * $top[3] + $under[1] * (1 - $top[3]), $top[2] * $top[3] + $under[2] * (1 - $top[3]), 1.0];
}

function shellContrast(array $a, array $b): float
{
    [$light, $dark] = shellLuminance($a) >= shellLuminance($b) ? [$a, $b] : [$b, $a];

    return (shellLuminance($light) + 0.05) / (shellLuminance($dark) + 0.05);
}

function shellEnvironment(callable $run): void
{
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        $run();
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
}

it('always has the workspace switcher in the bar (one workspace or two) beside the symbol only, and a long name or wide mark never skews the bar', function (): void {
    shellEnvironment(function (): void {
        $page = shellSignIn();
        $workspace = Workspace::query()->firstOrFail();
        $page->resize(1440, 900)->navigate('/settings/people')->assertPresent('[data-shell-switcher]');
        // The bar is the product symbol and the switcher: no product-name text, no separator.
        expect(trim((string) $page->script('() => document.querySelector("[data-shell-wordmark]").textContent')))->toBe('');
        expect((string) $page->script('() => document.querySelector("[data-shell-bar] > div").textContent'))->not->toContain('/')->not->toContain(config('app.name'));
        expect(trim((string) $page->script('() => document.querySelector("[data-shell-workspace]").textContent')))->toBe($workspace->name);
        // With one workspace its menu is Workspace settings (the server sent that action), then all workspaces.
        $page->click('[data-shell-switcher]')->assertSee('Workspace settings');
        $page->script('() => { document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" })); return true; }');

        // A 120-character name with no spaces, at three widths: the bar and sub-bar keep their heights, nothing overflows, the name is truncated with the full name in `title`.
        $long = str_repeat('Wonderful', 13).'WWW';
        expect(strlen($long))->toBe(120);
        $measure = fn (): array => $page->script(shellScript('const h = (s) => Math.round($(s)?.getBoundingClientRect().height ?? 0); const row = $("[data-shell-bar] > div"); const name = $("[data-shell-workspace]"); const viewport = window.innerWidth; const inside = (s) => { const r = $(s)?.getBoundingClientRect(); return !r || (r.left >= 0 && r.right <= viewport + 0.5); }; return { bar: h("[data-shell-bar]"), sub: h("[data-shell-subbar]"), rowFits: row.scrollWidth <= row.clientWidth, pageFits: document.documentElement.scrollWidth <= viewport, crumbsFit: !$("[data-shell-breadcrumbs]") || $("[data-shell-breadcrumbs]").scrollWidth <= $("[data-shell-breadcrumbs]").clientWidth, menuIn: inside("[data-shell-menu-button]"), userIn: inside("[data-shell-user-menu]"), title: $("[data-shell-home]").getAttribute("title"), clipped: name.scrollWidth > name.clientWidth };'));
        $baseline = [];
        foreach ([390, 820, 1440] as $width) {
            $page->resize($width, 900)->navigate('/settings/people')->assertPresent('[data-shell-switcher]');
            $baseline[$width] = $measure();
        }
        $workspace->forceFill(['name' => $long])->save();
        foreach ([390, 820, 1440] as $width) {
            $page->resize($width, 900)->navigate('/settings/people')->assertPresent('[data-shell-switcher]');
            $now = $measure();
            expect([$now['bar'], $now['sub']])->toBe([$baseline[$width]['bar'], $baseline[$width]['sub']], $width.': the bars keep their heights');
            expect([$now['rowFits'], $now['pageFits'], $now['crumbsFit']])->toBe([true, true, true], $width.': nothing overflows');
            expect($now['title'])->toBe($long);
            expect($now['clipped'])->toBeTrue($width.': truncated with an ellipsis');
            if ($width < 1024) {
                expect([$now['menuIn'], $now['userIn'] ?? true])->toBe([true, true], $width.': the Menu toggle stays on screen');
            }
        }
        // A very wide logomark at a phone width, with the long name: the mark scales inside its capped box, the controls stay inside the viewport and the bar keeps its height.
        file_put_contents(public_path('wide-mark-test.svg'), '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 100" width="1200" height="100"><rect width="1200" height="100" fill="#0f766e"/></svg>');
        try {
            config(['kit.brand.logomark' => '/wide-mark-test.svg']);
            $page->resize(390, 900)->navigate('/settings/people')->assertPresent('[data-shell-switcher]');
            $wide = $measure();
            expect($wide['bar'])->toBe($baseline[390]['bar']);
            expect([$wide['menuIn'], $wide['pageFits'], $wide['rowFits']])->toBe([true, true, true]);
            $mark = $page->script(shellScript('const r = $("[data-shell-wordmark]").getBoundingClientRect(); const img = $("[data-shell-wordmark] img").getBoundingClientRect(); return { width: r.width, imgRight: img.right, markRight: r.right, imgHeight: img.height };'));
            expect($mark['width'])->toBeLessThanOrEqual(112.5);
            expect($mark['imgRight'])->toBeLessThanOrEqual($mark['markRight'] + 0.5);
        } finally {
            @unlink(public_path('wide-mark-test.svg'));
            config(['kit.brand.logomark' => '/logomark.svg']);
            $workspace->forceFill(['name' => 'Local workspace'])->save();
        }

        // With two workspaces the bar carries the switcher instead of the plain name.
        $owner = ConciergeModels::user()::query()->where('email', 'owner@example.com')->firstOrFail();
        $other = ConciergeModels::user()::query()->create(['name' => 'Second owner', 'email' => 'second-owner@example.test', 'email_verified_at' => now(), 'password' => Hash::make('x')]);
        $second = app(AcceptWorkspaceInvitation::class)->handle(new Principal((int) $other->id), InviteTenant::run($owner, $other->email)->token)['workspace_id'];
        Workspace::query()->whereKey($second)->update(['name' => 'Second workspace']);
        AcceptInvitation::run($owner, InviteMember::run($other, Workspace::findOrFail($second), $owner->email, new GrantSet(['viewer']))->token);
        $page->resize(1440, 900)->navigate('/');
        $page->assertPresent('[data-shell-switcher]')->assertSee('Local workspace');
        $page->click('[data-shell-switcher]')->assertSee('Second workspace')->assertSee('Workspace settings');
    });
});

it('renders the footer as one slim line whose links are separated by a visible dot a screen reader skips', function (): void {
    shellEnvironment(function (): void {
        $page = shellSignIn();
        $read = '() => { const f = document.querySelector("[data-shell-footer]"); const seps = [...f.querySelectorAll("[data-shell-footer-separator]")]; const links = [...f.querySelectorAll("a")]; return { text: f.innerText, lines: new Set([...f.children].map((c) => Math.round(c.getBoundingClientRect().top))).size, links: links.map((a) => a.textContent.trim()), seps: seps.map((s) => { const r = s.getBoundingClientRect(); const prev = s.previousElementSibling.getBoundingClientRect(); const next = s.nextElementSibling.getBoundingClientRect(); return { hidden: s.getAttribute("aria-hidden"), width: r.width, before: r.top === prev.top ? r.left - prev.right : null, after: r.top === next.top ? next.left - r.right : null }; }), overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth }; }';

        $wide = $page->resize(1280, 900)->script($read);
        // The links come from the brand config, so read their labels rather than pinning them.
        $links = $wide['links'];
        expect(count($links))->toBeGreaterThanOrEqual(2);
        expect($wide['text'])->toContain(implode(' · ', $links))->not->toContain("\n");
        expect($wide['lines'])->toBe(1);
        foreach ($wide['seps'] as $separator) {
            expect($separator['hidden'])->toBe('true');
            expect($separator['width'])->toBeGreaterThan(0);
            // A real gap on both sides, not a dot flush against the words.
            expect($separator['before'])->toBeGreaterThan(0);
            expect($separator['after'])->toBeGreaterThan(0);
        }

        $phone = $page->resize(375, 800)->script($read);
        expect($phone['overflow'])->toBeFalse();
        expect(str_replace("\n", ' ', $phone['text']))->toContain(implode(' · ', $links));
    });
});
