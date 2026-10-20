<?php

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;

// FormActions in a real browser, on General settings (Inertia's <Form> + FormCard + FormActions, no useForm): each state from the form context alone,
// the order of status, Cancel and Save, the leave warning, the sticky bar tinted only while pinned, and a Reka control submitting its named value.
// Not covered here: upload progress (the harness corrupts binary uploads, see tests/AvatarTest.php); the spec's source-level checks are in tests/SettingsFormsTest.php.
it('shows the form states, keeps the order, warns on leave and pins only while the end is off-screen', function (): void {
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

        $page = visit('/login')->click('[data-local-login] button')->assertPathIs('/dashboard');
        $page->navigate('/settings/general')->assertPresent('[data-form-actions]');
        $status = fn (): array => $page->script('() => { const s = document.querySelector("[data-form-status]"); return [s.dataset.state, s.textContent.trim(), s.className]; }');
        // Every state seen, whenever it happens: recorded as the DOM changes.
        $page->script('() => { window.__seen = []; new MutationObserver(() => { const s = document.querySelector("[data-form-status]"); const b = document.querySelector("[data-form-submit]"); if (s) window.__seen.push(s.dataset.state + ":" + s.textContent.trim() + ":" + (b && b.getAttribute("aria-busy") ? "busy" : "")); }).observe(document.body, { subtree: true, childList: true, attributes: true, characterData: true }); return true; }');
        $unload = fn (): bool => (bool) $page->script('() => { const e = new Event("beforeunload", { cancelable: true }); window.dispatchEvent(e); return e.defaultPrevented; }');

        // Idle: nothing to say, and its space is kept.
        [$state, $text] = $status();
        expect([$state, $text])->toBe(['idle', '']);
        expect($unload())->toBeFalse();
        // Dirty after typing: a neutral muted text, never the danger colour; closing the tab asks.
        $page->type('#display-name', 'Renamed workspace');
        [$state, $text, $class] = $status();
        expect([$state, $text])->toBe(['dirty', 'Unsaved changes']);
        expect($class)->toContain('text-muted-foreground')->not->toContain('destructive');
        expect($unload())->toBeTrue();
        // Cancel resets the fields and the status.
        $page->click('[data-form-cancel]');
        expect($page->script('() => document.querySelector("#display-name").value'))->not->toBe('Renamed workspace');
        expect($status()[0])->toBe('idle');
        expect($unload())->toBeFalse();

        // Leaving while dirty asks (the kit's dialog, not window.confirm); Stay keeps the page and the edit.
        $page->type('#display-name', 'Kept edit')->click('[data-shell-nav] a[href="/dashboard"]')->assertPresent('[data-confirm-dialog]');
        $page->click('[data-confirm-dialog] button:first-of-type')->assertMissing('[data-confirm-dialog]')->assertPathIs('/settings/general');
        expect($page->script('() => document.querySelector("#display-name").value'))->toBe('Kept edit');
        // Submitting does not prompt: busy, then saved, and the page keeps the new value.
        $page->click('[data-form-submit]')->assertMissing('[data-confirm-dialog]')->assertPathIs('/settings/general');
        $page->script('() => new Promise((resolve) => setTimeout(resolve, 600))');
        $seen = $page->script('() => window.__seen');
        expect(implode('|', $seen))->toContain('saved:Saved')->toContain('busy')->not->toContain('errors');
        expect($unload())->toBeFalse();

        // Errors from the server's validation: the danger colour, in the spec's words; Cancel clears them.
        $page->script('() => { document.querySelector("#display-name").removeAttribute("maxlength"); return true; }');
        $page->type('#display-name', str_repeat('x', 130))->click('[data-form-submit]');
        $page->script('() => new Promise((resolve) => setTimeout(resolve, 600))');
        [$state, $text, $class] = $status();
        expect([$state, $text])->toBe(['errors', 'There are errors in your submission.']);
        expect($class)->toContain('text-destructive');
        $page->assertPresent('[role="alert"]');
        $page->click('[data-form-cancel]');
        expect($status()[0])->not->toBe('errors');
        $page->assertMissing('#display-name-error');

        // A Reka control inside the uncontrolled <Form> submits its named value, and choosing another makes the form dirty.
        $page->click('#timezone')->click('[role="option"]:has-text("Europe/London")');
        expect($page->script('() => new FormData(document.querySelector("form")).get("timezone")'))->toBe('Europe/London');
        expect($status()[0])->toBe('dirty');
        $page->click('[data-form-cancel]');
        expect($page->script('() => new FormData(document.querySelector("form")).get("timezone")'))->not->toBe('Europe/London');
        expect($page->script('() => new FormData(document.querySelector("form")).get("timezone")'))->not->toBeEmpty();
        expect($page->script('() => new FormData(document.querySelector("form")).get("display_name")'))->not->toBeEmpty();

        // Status, Cancel, Save: in the DOM and on screen (left to right, top to bottom), at desktop and phone widths.
        $order = fn (): bool => (bool) $page->script('() => { const bar = document.querySelector("[data-form-actions]"); const els = ["[data-form-status]", "[data-form-cancel]", "[data-form-submit]"].map((q) => bar.querySelector(q)); const pos = els.map((e) => e.getBoundingClientRect()); const dom = els[0].compareDocumentPosition(els[1]) & 4 && els[1].compareDocumentPosition(els[2]) & 4; const key = (r) => Math.round((r.top + r.height / 2) / 40) * 100000 + r.left; const visual = key(pos[0]) < key(pos[1]) && key(pos[1]) < key(pos[2]); return !!dom && visual; }');
        expect($order())->toBeTrue();
        $page->resize(390, 844);
        expect($order())->toBeTrue();

        // Sticky: pinned and tinted while the form's end is below the viewport; released, with no tint, once the end is in view.
        $page->resize(1000, 280);
        $bar = fn (): array => $page->script('() => { const b = document.querySelector("[data-form-actions]"); return [b.dataset.pinned, getComputedStyle(b).backgroundColor, getComputedStyle(b).position]; }');
        $page->script('() => { window.scrollTo(0, 0); return true; }');
        $page->script('() => new Promise((resolve) => setTimeout(resolve, 300))');
        [$pinned, $background, $position] = $bar();
        expect([$pinned, $position])->toBe(['true', 'sticky']);
        expect($background)->not->toBe('rgba(0, 0, 0, 0)');
        $page->script('() => { window.scrollTo(0, document.documentElement.scrollHeight); return true; }');
        $page->script('() => new Promise((resolve) => setTimeout(resolve, 300))');
        [$pinned, $background] = $bar();
        expect($pinned)->toBe('false');
        expect($background)->toBe('rgba(0, 0, 0, 0)');
        $page->assertNoJavaScriptErrors();

        // The other kit forms render on the same pieces, without script errors.
        $page->resize(1440, 900)->navigate('/settings/people')->assertPresent('[data-form-card] [data-form-actions]')->assertSee('Send invitation')->assertNoJavaScriptErrors();
        $page->navigate('/me/profile')->assertPresent('[data-form-card] [data-form-actions]')->assertSee('Upload photo')->assertNoJavaScriptErrors();
        $page->navigate('/settings/connections')->assertSee('Connections')->assertNoJavaScriptErrors();
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
