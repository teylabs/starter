<?php

use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\Workspaces\Models\Workspace;

// The kit's FormModal in a real browser, on People's Invite member and Change role: the modal opens over People from its server URL (also when that URL is opened directly), validation
// errors stay in it, a successful submit closes it and refreshes People, closing with unsaved edits asks first, and the backdrop never closes a form.
it('opens, validates, submits and closes the form modals over People', function (): void {
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
        $owner = ConciergeModels::user()::query()->where('email', $example['CONCIERGE_LOCAL_LOGIN_EMAIL'])->firstOrFail();
        $owner->forceFill(['password' => Hash::make('modal-test-pass-1')])->save();
        $user = User::query()->where('email', 'modal-member@example.test')->first() ?? User::create(['name' => 'Modal Member', 'email' => 'modal-member@example.test', 'email_verified_at' => now(), 'password' => Hash::make('x')]);
        if ($user->wasRecentlyCreated) {
            AcceptInvitation::run($user, InviteMember::run($owner, Workspace::query()->firstOrFail(), $user->email, new GrantSet(['viewer']))->token);
        }

        $page = visit('/login')->click('[data-local-login] button')->assertPathIs('/dashboard');
        $page->resize(1440, 900)->navigate('/settings/people')->assertSee('People');
        $count = fn (string $tab): string => (string) $page->script('() => document.querySelector(`[data-tab="'.$tab.'"] [data-tab-count]`).textContent.trim()');

        // Opening: the modal is over People, at its own URL, with focus in the first field.
        $page->click('[data-invite]')->assertPresent('[data-form-modal]')->assertPathIs('/settings/people/invitations')->assertSee('Invite member');
        $page->script('() => new Promise((resolve) => setTimeout(() => resolve(true), 500))');
        expect((string) $page->script('() => document.activeElement?.id'))->toBe('invite-email');
        // The backdrop never closes a form: a click outside leaves it open.
        $page->script('() => { const backdrop = document.elementFromPoint(8, 8); backdrop?.dispatchEvent(new MouseEvent("click", { bubbles: true })); return true; }');
        $page->assertPresent('[data-form-modal]');
        // A server validation error (already a member) stays in the modal.
        $page->type('#invite-email', 'modal-member@example.test')->click('[data-form-submit]');
        $page->script('() => new Promise((resolve) => setTimeout(() => resolve(true), 800))');
        $page->assertPresent('[data-form-modal]')->assertPathIs('/settings/people/invitations');
        expect((int) $page->script('() => document.querySelectorAll("[data-form-modal] [id$=-error]").length'))->toBeGreaterThan(0);
        // Closing with edits asks first; "Keep editing" stays, "Discard & close" leaves.
        $page->click('[data-modal-cancel]')->assertPresent('[data-confirm-dialog]')->assertSee('Keep editing');
        $page->click('[data-cancel-action]')->assertPresent('[data-form-modal]');
        $page->script('() => { document.querySelector("#invite-email").dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true })); return true; }');
        $page->assertPresent('[data-confirm-dialog]')->click('[data-confirm-action]')->assertMissing('[data-form-modal]');
        $page->script('() => new Promise((resolve) => setTimeout(() => resolve(true), 600))');
        $page->assertPathIs('/settings/people');
        expect($count('invitations'))->toBe('0');

        // Success closes the modal and People refreshes (the invitation count moves).
        $page->click('[data-invite]')->assertPresent('[data-form-modal]');
        $page->type('#invite-email', 'new.person@example.test')->click('[data-form-submit]');
        $page->assertMissing('[data-form-modal]');
        $page->script('() => new Promise((resolve) => setTimeout(() => resolve(true), 600))');
        $page->assertPathIs('/settings/people');
        expect($count('invitations'))->toBe('1');

        // Opened directly, the URL shows People with the modal on top.
        $page->navigate('/settings/people/invitations')->assertPresent('[data-form-modal]')->assertSee('People');
        $page->click('[data-modal-close]')->assertMissing('[data-form-modal]');
        $page->script('() => new Promise((resolve) => setTimeout(() => resolve(true), 600))');
        $page->assertPathIs('/settings/people');

        // On a phone a small form modal is a bottom sheet: full width, its bottom on the viewport's bottom, rounded on top only.
        $page->resize(390, 844)->navigate('/settings/people')->click('[data-invite]')->assertPresent('[data-form-modal]');
        $page->script('() => new Promise((resolve) => setTimeout(() => resolve(true), 600))');
        $sheet = $page->script('() => { const panel = document.querySelector("[data-form-modal]").closest(".im-modal-content"); const r = panel.getBoundingClientRect(); const s = getComputedStyle(panel); return { left: Math.round(r.left), right: Math.round(r.right), bottom: Math.round(r.bottom), viewport: window.innerHeight, clientWidth: panel.closest(".im-modal-container").clientWidth, topRadius: s.borderTopLeftRadius, bottomRadius: s.borderBottomLeftRadius, scrolls: document.documentElement.scrollWidth <= window.innerWidth }; }');
        expect([$sheet['left'], $sheet['right'], $sheet['bottom']])->toBe([0, $sheet['clientWidth'], $sheet['viewport']]);
        expect($sheet['topRadius'])->not->toBe('0px')->and($sheet['bottomRadius'])->toBe('0px')->and($sheet['scrolls'])->toBeTrue();
        $page->click('[data-modal-close]')->assertMissing('[data-form-modal]');
        $page->resize(1440, 900)->navigate('/settings/people');

        // Change role is the same pattern, from the row menu.
        $page->click('[data-row]:has-text("Modal Member") [data-action="change-role"]')->assertPresent('[data-form-modal]')->assertSee('Change role for Modal Member');
        $page->click('[data-form-submit]')->assertMissing('[data-form-modal]')->assertNoJavaScriptErrors();
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});

// The same <Modal> and <Dialog> used locally, with v-model:open (no server route): they open, close from their own controls, and the modal asks before discarding edits.
it('works locally with v-model:open: the same Modal and Dialog', function (): void {
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
        app('router')->middleware(['web', 'auth:web', 'concierge.active'])->get('/_test/local-modal', fn () => Inertia\Inertia::render('Workspace/Examples/LocalModal/Page', ['title' => 'Local modal']));
        $page = visit('/login')->click('[data-local-login] button')->assertPathIs('/dashboard');
        $page->resize(1440, 900)->navigate('/_test/local-modal')->assertPresent('[data-open-local]');
        $page->click('[data-open-local]')->assertSee('A local modal')->assertSee('Local content.');
        $page->click('[data-local-done]')->assertDontSee('A local modal');
        // Edits make closing ask; the ask is a Dialog over the modal.
        $page->click('[data-open-local]')->assertSee('A local modal');
        $page->click('[data-make-dirty]')->click('[data-modal-close]')->assertPresent('[data-confirm-dialog]')->assertSee('Keep editing');
        $page->click('[data-cancel-action]')->assertSee('A local modal');
        $page->click('[data-modal-close]')->click('[data-confirm-action]')->assertDontSee('A local modal');
        // A disabled menu item shows not-allowed (it is not pointer-events: none) and still does nothing when clicked; a usable one shows the pointer.
        $page->click('[data-open-menu]')->assertPresent('[data-disabled-item]');
        $cursors = $page->script('() => [getComputedStyle(document.querySelector("[data-enabled-item]")).cursor, getComputedStyle(document.querySelector("[data-disabled-item]")).cursor, getComputedStyle(document.querySelector("[data-open-menu]")).cursor]');
        expect($cursors)->toBe(['pointer', 'not-allowed', 'pointer']);
        $page->script('() => { document.querySelector("[data-disabled-item]").click(); return true; }');
        expect((string) $page->script('() => document.querySelector("[data-picked]").textContent.trim()'))->toBe('0');
        $page->click('[data-enabled-item]');
        expect((string) $page->script('() => document.querySelector("[data-picked]").textContent.trim()'))->toBe('1');
        // A Dialog is a confirmation: static, and it closes from its own buttons.
        $page->click('[data-open-dialog]')->assertSee('Delete it?')->click('[data-confirm-dialog] button:has-text("Cancel")')->assertDontSee('Delete it?')->assertNoJavaScriptErrors();
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
