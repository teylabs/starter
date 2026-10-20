<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Concierge\Actions\InviteTenant;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\Operations\AcceptWorkspaceInvitation;

// Smoke: the designed sign-in page loads, the owner signs in, the app shell lists their workspace and opens it, and
// their name is in the user menu, not the nav.
it('signs the owner in to the app shell', function (): void {
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
    expect(app(Kernel::class)->call('concierge:sync-roles', ['--no-interaction' => true]))->toBe(0);
    $password = Str::random(32);
    $owner = User::create(['name' => 'Ada Owner', 'email' => 'owner@example.test', 'email_verified_at' => now(), 'password' => Hash::make($password)]);
    DB::table('operators')->insert(['actor_id' => $owner->id]);
    $workspace = app(AcceptWorkspaceInvitation::class)->handle(new Principal((int) $owner->id), InviteTenant::run($owner, $owner->email)->token)['workspace_id'];
    Workspace::query()->whereKey($workspace)->update(['name' => 'Acme']);

    $page = visit('/login')
        ->assertSee('Sign in to your account')
        ->assertPresent('[data-guest-card]')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'sign-in');

    $page->type('email', 'owner@example.test')
        ->type('password', $password)
        ->press('Sign in')
        ->assertPathIs('/workspaces')
        ->assertPresent('[data-shell-bar]')
        ->assertSeeIn('[data-shell-nav]', 'Workspaces')
        ->assertSeeIn('[data-shell-nav]', 'Acme')
        ->assertDontSeeIn('[data-shell-nav]', 'Ada Owner')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'app-shell');

    $page->click('[data-shell-user-menu]')
        ->assertSee('Ada Owner')
        ->assertSee('owner@example.test')
        ->screenshot(filename: 'user-menu');

    $page->keys('[role=menu]', 'Escape')
        ->click('Acme')
        ->assertPathIs("/workspaces/{$workspace}/settings")
        ->assertSee('Workspace settings')
        ->assertSeeIn('[data-shell-nav]', 'Settings')
        ->assertSeeIn('[data-shell-nav]', 'Notes')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'workspace');
});
