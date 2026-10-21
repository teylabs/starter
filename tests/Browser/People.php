<?php

require_once __DIR__.'/../Concerns/FreshClone.php';

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\FreshClone;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\Workspaces\Models\Workspace;

// The People index in a real browser: the Members and Invitations tabs (the tab is in the URL, with server counts), the debounced search, the row menu (a plain action
// inline, Remove in the menu behind a confirmation), the change-role and invite dialogs, and the stacked-card layout on a phone.
it('lists people in tabs with search, a row menu and the invite dialog', function (): void {
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        config(['concierge.local_login' => FreshClone::localLogin('local')]);
        app()->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
        app('router')->getRoutes()->refreshNameLookups();
        expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
        expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);
        $owner = ConciergeModels::user()::query()->where('email', config('concierge.local_login.account.email'))->firstOrFail();
        $owner->forceFill(['password' => Hash::make('people-test-pass-1')])->save();
        $workspace = Workspace::query()->firstOrFail();
        // A viewer to act on, and a lot of members so the list pages.
        foreach (['Vera Viewer' => 'viewer@example.test', 'Walt Watcher' => 'watcher@example.test'] as $name => $email) {
            $user = User::create(['name' => $name, 'email' => $email, 'email_verified_at' => now(), 'password' => Hash::make('x')]);
            AcceptInvitation::run($user, InviteMember::run($owner, $workspace, $email, new GrantSet(['viewer']))->token);
        }

        $page = visit('/login')->click('[data-local-login] button')->assertPathIs('/dashboard');
        $page->navigate('/settings/people')->assertSee('Members')->assertSee('Vera Viewer')->assertPresent('[data-data-table]')->assertNoJavaScriptErrors();
        $count = fn (string $tab): string => (string) $page->script('() => document.querySelector(`[data-tab="'.$tab.'"] [data-tab-count]`).textContent.trim()');
        expect([$count('members'), $count('invitations')])->toBe(['3', '0']);
        $page->assertPresent('[data-tab="members"][aria-current="page"]');

        // The tab is in the URL; an empty tab is the first-use state with its own words, and the toolbar is hidden.
        $page->click('[data-tab="invitations"]')->assertQueryStringHas('tab', 'invitations')->assertSee('No invitations yet')->assertPresent('[data-empty="first-use"]')->assertMissing('[data-table-toolbar]');
        $page->script('() => { history.back(); return true; }');
        $page->assertPresent('[data-tab="members"][aria-current="page"]')->assertSee('Vera Viewer');

        // The invite dialog: Form + FormActions; the invitation lands, the dialog closes and the server count moves.
        $page->click('[data-invite]')->assertPresent('[data-form-modal]')->assertSee('Invite member');
        $page->type('#invite-email', 'new.person@example.test')->click('[data-form-submit]');
        $page->assertMissing('[data-form-modal]');
        expect($count('invitations'))->toBe('1');
        $page->click('[data-tab="invitations"]')->assertSee('new.person@example.test')->assertQueryStringHas('tab', 'invitations');
        // Revoke is behind the menu or a lone destructive button, and asks first.
        $page->click('[data-row-menu]')->click('[data-action="revoke"]')->assertPresent('[data-confirm-dialog]')->assertSee('Revoke the invitation to new.person@example.test?');
        $page->click('[data-confirm-action]');
        $page->assertSee('No invitations yet');
        expect($count('invitations'))->toBe('0');

        // Search is debounced: typing alone changes the URL (replace), and the rows follow; no results has its own state and a way back.
        $page->navigate('/settings/people');
        $page->type('[data-table-search]', 'vera');
        $page->assertSee('Vera Viewer')->assertDontSee('Walt Watcher');
        expect(urldecode((string) $page->script('() => window.location.search')))->toBe('?filter[search]=vera');
        $page->clear('[data-table-search]')->type('[data-table-search]', 'nobody');
        $page->assertSee('No members match')->assertPresent('[data-clear-search]');
        $page->click('[data-clear-search]')->assertSee('Walt Watcher');

        // The row menu: the owner has none; the viewer's Change role is inline and opens a dialog, Remove is in the menu and confirms first.
        $page->navigate('/settings/people')->assertSee($owner->name);
        $actionsOn = fn (string $name): int => (int) $page->script('() => [...document.querySelectorAll("[data-row]")].find((row) => row.textContent.includes("'.$name.'"))?.querySelectorAll("[data-row-actions] button").length ?? -1');
        expect($actionsOn($owner->name))->toBe(0);
        $page->click('[data-row]:has-text("Vera") [data-action="change-role"]')->assertPresent('[data-form-modal]')->assertSee('Change role for');
        $page->click('[data-modal-cancel]')->assertMissing('[data-form-modal]');
        $page->click('[data-row]:has-text("Vera") [data-row-menu]')->click('[data-action="remove"]')->assertPresent('[data-confirm-dialog]')->assertSee('They lose access to this workspace straight away.');
        $page->click('[data-confirm-action]')->assertDontSee('Vera Viewer')->assertNoJavaScriptErrors();

        // Sort by the Member column (ascending by default): it sends the server's own "name" sort, not the column key, with no error modal; a second click returns to the default.
        $page->click('[data-sort="member"]')->assertQueryStringHas('sort', '-name')->assertMissing('[data-inertia-error], iframe')->assertNoJavaScriptErrors();
        expect((string) $page->script('() => document.querySelector("th[data-column=member]").getAttribute("aria-sort")'))->toBe('descending');
        $page->click('[data-sort="member"]');
        $page->script('() => new Promise((resolve) => setTimeout(() => resolve(true), 500))');
        expect(urldecode((string) $page->script('() => window.location.search')))->not->toContain('sort=');
        expect((string) $page->script('() => document.querySelector("th[data-column=member]").getAttribute("aria-sort")'))->toBe('ascending');
        // Sort: the header button sets ?sort= and aria-sort follows; the order is the server's.
        $page->click('[data-sort="joined"]')->assertQueryStringHas('sort', 'joined');
        expect((string) $page->script('() => document.querySelector("th[data-column=joined]").getAttribute("aria-sort")'))->toBe('ascending');

        // A phone: each row is a stacked card and nothing scrolls sideways.
        $page->resize(390, 844)->navigate('/settings/people');
        expect((bool) $page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
        expect((string) $page->script('() => getComputedStyle(document.querySelector("[data-row]")).display'))->toBe('grid');
        $page->assertNoJavaScriptErrors();
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
