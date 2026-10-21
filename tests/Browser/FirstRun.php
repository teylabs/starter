<?php

use Illuminate\Contracts\Console\Kernel;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Concierge;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\Workspaces\Models\Workspace;

// The invitee's first run in a real browser: the invitation creates the account and joins, the FocusShell walks the steps ("Step n of m"), skipping never blocks,
// and the dashboard greets them once, with the welcome card and the checklist of what was skipped.
it('walks an invitee from the invitation through the welcome steps to the dashboard card', function (): void {
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
        expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);
        $workspace = Workspace::query()->firstOrFail();
        $token = InviteMember::run(app(Concierge::class)->ownerOf($workspace)->user, $workspace, 'browser.person@example.test', new GrantSet(['viewer']), 'Glad to have you.')->token;

        $page = visit('/invitations/'.$token)->assertSee('Join '.$workspace->name)->assertPresent('[data-guest-card]');
        // The locked email reads as read-only, not as an editable field: a muted surface, the default cursor, in both themes; it is still a native readonly input.
        foreach (['light', 'dark'] as $theme) {
            $page->script('() => document.documentElement.classList.toggle("dark", '.($theme === 'dark' ? 'true' : 'false').')');
            $look = $page->script('() => { const a = getComputedStyle(document.getElementById("invite-email")); const b = getComputedStyle(document.getElementById("name")); return { readonly: document.getElementById("invite-email").readOnly, cursor: a.cursor, bg: a.backgroundColor, editableBg: b.backgroundColor, editableCursor: b.cursor }; }');
            expect($look['readonly'])->toBeTrue()->and($look['cursor'])->toBe('default')->and($look['bg'])->not->toBe($look['editableBg']);
        }
        $page->script('() => document.documentElement.classList.remove("dark")');
        // The first editable field has the focus on arrival: the name, never the locked email.
        expect($page->script('() => document.activeElement && document.activeElement.id'))->toBe('name');
        $page->fill('name', 'Bea Browser')->fill('password', 'a-long-password-1')->fill('password_confirmation', 'a-long-password-1')
            ->click('[data-test="invitation-register"]')
            ->assertPathIs('/welcome/timezone')
            ->assertPresent('[data-focus-shell] [data-focus-step]')
            ->assertSeeIn('[data-focus-step]', 'Step 1 of 2')
            ->assertScript('document.activeElement.id', 'timezone')
            ->assertMissing('[data-shell-bar]')
            ->assertNoJavaScriptErrors();
        $page->click('[data-welcome-submit]')->assertPathIs('/welcome/photo')->assertSeeIn('[data-focus-step]', 'Step 2 of 2')->assertScript('document.activeElement.id', 'avatar');
        foreach ([[390, 844], [1440, 900]] as [$width, $height]) {
            $page->resize($width, $height);
            expect($page->script('() => document.documentElement.scrollWidth - document.documentElement.clientWidth'))->toBeLessThanOrEqual(0);
        }
        $page->click('[data-welcome-skip]')
            ->assertPathBeginsWith('/dashboard')
            ->assertSeeIn('[data-slot="page-heading"]', 'Good')
            ->assertSeeIn('[data-welcome-title]', 'Welcome to')->assertSeeIn('[data-welcome-title]', 'Bea')->assertPresent('[data-welcome-card] [role="progressbar"]')
            ->assertSeeIn('[data-welcome-card]', 'Your workspace for '.$workspace->name.' is ready.')
            ->assertPresent('[data-welcome-checklist]')
            ->assertPresent('[data-shell-bar]')
            ->assertNoJavaScriptErrors();
        foreach ([[390, 844], [1440, 900]] as [$width, $height]) {
            foreach (['light', 'dark'] as $theme) {
                $page->resize($width, $height)->script('() => document.documentElement.classList.toggle("dark", '.($theme === 'dark' ? 'true' : 'false').')');
                expect($page->script('() => document.documentElement.scrollWidth - document.documentElement.clientWidth'))->toBeLessThanOrEqual(0);
                // Each checklist row is a comfortable tap target.
                expect($page->script('() => Math.min(...[...document.querySelectorAll("[data-welcome-checklist] li a")].map((a) => a.getBoundingClientRect().height))'))->toBeGreaterThanOrEqual(44);
            }
        }
        $page->assertSeeIn('[data-welcome-progress]', '1 of 2 done')->click('[data-welcome-dismiss]')->assertMissing('[data-welcome-card]');
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
