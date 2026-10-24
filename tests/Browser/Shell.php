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

it('draws the breadcrumb sub-bar as its own translucent surface that stays readable over any content, in light and dark', function (): void {
    shellEnvironment(function (): void {
        $page = shellSignIn();
        $page->resize(1440, 900)->navigate('/settings/people')->assertPresent('[data-shell-subbar]')->assertNoJavaScriptErrors();
        foreach (['light', 'dark'] as $mode) {
            $page->script('() => { document.documentElement.classList.toggle("dark", '.($mode === 'dark' ? 'true' : 'false').'); return true; }');
            $read = $page->script(shellScript('const sub = getComputedStyle($("[data-shell-subbar]")); const crumb = getComputedStyle($("[data-shell-breadcrumbs] li:last-child a")); const first = getComputedStyle($("[data-shell-breadcrumbs] li:nth-child(2) a")); return { bar: norm(getComputedStyle($("[data-shell-bar]")).backgroundColor), sub: norm(sub.backgroundColor), page: norm(getComputedStyle(document.body).backgroundColor), border: norm(sub.borderBottomColor), width: sub.borderBottomWidth, last: norm(crumb.color), other: norm(first.color), blur: sub.backdropFilter };'));
            expect($read['sub'])->not->toBe($read['bar'], $mode.': the sub-bar is not the header');
            expect($read['sub'])->not->toBe($read['page'], $mode.': the sub-bar is not the page');
            expect($read['sub'][3])->toBeGreaterThan(0.5)->toBeLessThan(0.95, $mode.': translucent, not clear and not solid');
            expect($read['width'])->toBe('1px');
            expect($read['border'][3])->toBeGreaterThan(0)->toBeLessThan(0.3, $mode.': a faint hairline');
            expect($read['blur'])->toContain('blur');
            // Readable over the worst case beneath: pure black and pure white content.
            foreach ([[0, 0, 0, 1.0], [255, 255, 255, 1.0]] as $under) {
                $surface = shellOver($read['sub'], $under);
                foreach (['last', 'other'] as $crumb) {
                    $ratio = shellContrast(shellOver($read[$crumb], $surface), $surface);
                    expect($ratio)->toBeGreaterThanOrEqual(4.5, $mode.' '.$crumb.' crumb over '.($under[0] === 0 ? 'black' : 'white').': '.round($ratio, 2));
                }
            }
        }
        $page->script('() => { document.documentElement.classList.remove("dark"); return true; }');
    });
});

it('reads correctly on a dark shell bar and the sub-bar does not follow it', function (): void {
    shellEnvironment(function (): void {
        $page = shellSignIn();
        $page->resize(1440, 900)->navigate('/settings/people')->assertNoJavaScriptErrors();
        $before = $page->script(shellScript('return norm(getComputedStyle($("[data-shell-subbar]")).backgroundColor);'));
        $page->script('() => { document.documentElement.style.setProperty("--shell-bar", "#111827"); document.documentElement.style.setProperty("--shell-bar-foreground", "#f9fafb"); return true; }');
        $page->script('() => new Promise((resolve) => setTimeout(() => resolve(true), 500))'); // let the colour transitions finish
        $read = $page->script(shellScript('const color = (s) => norm(getComputedStyle($(s)).color); return { bar: norm(getComputedStyle($("[data-shell-bar]")).backgroundColor), sub: norm(getComputedStyle($("[data-shell-subbar]")).backgroundColor), wordmark: color("[data-shell-wordmark]"), nav: color("[data-shell-nav] a[aria-current=page]"), navOther: color("[data-shell-nav] a:not([aria-current])"), appearance: color("[data-appearance-toggle]"), user: color("[data-shell-user-menu]"), workspace: color("[data-shell-workspace]") };'));
        expect($read['bar'])->toEqual([17, 24, 39, 1], 'the bar is exactly the token: solid, no fudge');
        expect($read['sub'])->toEqual($before, 'a dark header token does not change the sub-bar');
        foreach (['wordmark', 'nav', 'navOther', 'appearance', 'user', 'workspace'] as $control) {
            foreach ([249, 250, 251] as $channel => $expected) {
                expect(abs($read[$control][$channel] - $expected))->toBeLessThanOrEqual(2, $control.' draws from --shell-bar-foreground');
            }
            expect($read[$control][3])->toBeGreaterThanOrEqual(0.6);
            expect(shellContrast(shellOver($read[$control], $read['bar']), $read['bar']))->toBeGreaterThanOrEqual(4.5, $control.' reads on the dark bar');
        }
        // Tablet width: the collapsed Menu toggle is white-ish on the dark bar, not the light theme's dark text.
        $page->resize(820, 1000)->assertPresent('[data-shell-menu-button]');
        $menu = $page->script(shellScript('return { color: norm(getComputedStyle($("[data-shell-menu-button]")).color), background: norm(getComputedStyle($("[data-shell-menu-button]")).backgroundColor) };'));
        expect(array_slice($menu['color'], 0, 3))->toBe([249, 250, 251]);
        expect(shellContrast(shellOver($menu['color'], [17, 24, 39, 1.0]), [17, 24, 39, 1.0]))->toBeGreaterThanOrEqual(4.5);
        $page->assertNoJavaScriptErrors();
    });
});

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
        $measure = fn (): array => $page->script(shellScript('const h = (s) => Math.round($(s).getBoundingClientRect().height); const row = $("[data-shell-bar] > div"); const name = $("[data-shell-workspace]"); const viewport = window.innerWidth; const inside = (s) => { const r = $(s)?.getBoundingClientRect(); return !r || (r.left >= 0 && r.right <= viewport + 0.5); }; return { bar: h("[data-shell-bar]"), sub: h("[data-shell-subbar]"), rowFits: row.scrollWidth <= row.clientWidth, pageFits: document.documentElement.scrollWidth <= viewport, crumbsFit: $("[data-shell-breadcrumbs]").scrollWidth <= $("[data-shell-breadcrumbs]").clientWidth, menuIn: inside("[data-shell-menu-button]"), userIn: inside("[data-shell-user-menu]"), title: $("[data-shell-home]").getAttribute("title"), clipped: name.scrollWidth > name.clientWidth };'));
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
