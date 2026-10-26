<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Concierge;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\ClientTimezone;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\UserState;
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;
use Tey\Kit\Features\Settings\WorkspaceSettings;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->app['env'] = 'local';
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
    expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);
});
function firstRunInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
test('an invitee signs up walks the welcome steps and lands on the dashboard with the welcome card', function () {
    $workspace = Workspace::query()->firstOrFail();
    $owner = app(Concierge::class)->ownerOf($workspace)->user;
    $token = InviteMember::run($owner, $workspace, 'new.person@example.test', new GrantSet(['viewer']))->token;

    // One step: the account the invitation is for is created and the invitation accepted, straight into the welcome steps.
    $this->post('/invitations/'.$token.'/account', ['name' => 'Nora Newcomer', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'])->assertRedirect('/welcome');
    expect(User::query()->where('email', 'new.person@example.test')->count())->toBe(1);

    $this->get('/welcome')->assertRedirect('/welcome/timezone');
    $timezone = $this->get('/welcome/timezone', firstRunInertia())->assertOk();
    $timezone->assertJsonPath('component', 'Welcome/Timezone/Page')->assertJsonPath('props.shell.kind', 'focus')
        ->assertJsonPath('props.shell.step', ['current' => 1, 'total' => 2, 'label' => 'Your timezone']);
    $this->post('/welcome/timezone', ['timezone' => 'Not/AZone'])->assertSessionHasErrors('timezone');
    $this->post('/welcome/timezone', ['timezone' => 'Europe/Amsterdam'])->assertRedirect('/welcome');

    $this->get('/welcome', firstRunInertia())->assertRedirect('/welcome/photo');
    $this->get('/welcome/photo', firstRunInertia())->assertOk()->assertJsonPath('component', 'Welcome/Photo/Page')->assertJsonPath('props.shell.step.current', 2);

    // Skipping never blocks: the flow ends on the dashboard of the workspace they joined.
    $this->post('/welcome/photo/skip')->assertRedirect('/welcome');
    $this->get('/welcome')->assertRedirect(url(WorkspaceUrl::to($workspace, '', 'session-ui', request())));

    $dashboard = $this->get(WorkspaceUrl::to($workspace, '', 'session-ui', request()), firstRunInertia())->assertOk();
    expect($dashboard->json('props.greeting'))->toStartWith('Good ');
    expect($dashboard->json('props.greeting'))->toEndWith(', Nora');
    expect(UserState::has((int) User::query()->where('email', 'new.person@example.test')->value('id'), 'onboarding.done'))->toBeTrue();
    $dashboard->assertJsonPath('props.welcome.title', 'Welcome to '.config('app.name').', Nora.')
        ->assertJsonPath('props.welcome.body', 'Your workspace for '.$workspace->name.' is ready.')
        ->assertJsonPath('props.welcome.checklist', [['id' => 'timezone', 'label' => 'Your timezone', 'hint' => 'Confirm the timezone your dates and times show in', 'url' => '/welcome/timezone', 'state' => 'done'], ['id' => 'photo', 'label' => 'Your photo', 'hint' => 'Add a photo so teammates know who you are', 'url' => '/welcome/photo', 'state' => 'skipped']])
        ->assertJsonPath('props.welcome.done', 1)->assertJsonPath('props.welcome.total', 2)
        ->assertJsonPath('props.welcome.dismiss.label', 'Dismiss welcome')
        ->assertJsonPath('props.welcome.dismiss.url', '/me/welcome/'.$workspace->id);

    // Dismissing it removes the card for good, on every device.
    $this->delete('/me/welcome/'.$workspace->id)->assertRedirect();
    $after = $this->get(WorkspaceUrl::to($workspace, '', 'session-ui', request()), firstRunInertia())->assertOk();
    $after->assertJsonPath('props.welcome', null);
});
test('the demo command prints a fresh link each time and only runs locally', function () {
    expect(app(Kernel::class)->call('kit:first-run-demo'))->toBe(0);
    $first = app(Kernel::class)->output();
    expect(app(Kernel::class)->call('kit:first-run-demo'))->toBe(0);
    $second = app(Kernel::class)->output();
    $this->assertStringContainsString('demo+1@example.test', $first);
    $this->assertStringContainsString('demo+2@example.test', $second);
    $this->assertStringContainsString('/invitations/', $second);
    $this->app['env'] = 'production';
    expect(app(Kernel::class)->call('kit:first-run-demo'))->toBe(1);
});
/** the invitation path in the command's output for a fork */
function firstRunFork(string $name): string
{
    expect(app(Kernel::class)->call('kit:first-run-demo', ['--fork' => $name]))->toBe(0);
    $output = app(Kernel::class)->output();

    // A one-click sign-in link carries the invitation it opens as its `to`.
    if (preg_match('#https?://\S+/demo-login/\d+\?(\S+)#', $output, $signed)) {
        parse_str($signed[1], $query);

        return $query['to'];
    }
    preg_match('#/invitations/[A-Za-z0-9_-]+#', $output, $match);

    return $match[0] ?? '';
}
test('every invitation fork renders its own state', function () {
    $state = fn (string $path): ?string => $this->get($path, firstRunInertia())->assertOk()->json('props.state');
    expect($state(firstRunFork('new')))->toBe('register_required');
    expect($state(firstRunFork('existing-signed-out')))->toBe('sign_in_required');
    expect($state(firstRunFork('expired')))->toBe('expired');
    expect($state(firstRunFork('revoked')))->toBe('revoked');
    expect($state(firstRunFork('rotated')))->toBe('invalid');

    $invitee = firstRunFork('signed-in-as-invitee');
    $this->actingAs(User::query()->where('email', 'invitee+1@example.test')->firstOrFail());
    expect($state($invitee))->toBe('ready');
    $this->get($invitee, firstRunInertia())->assertJsonPath('props.shell.kind', 'focus');

    $other = firstRunFork('signed-in-as-other');
    $page = $this->get($other, firstRunInertia())->assertOk();

    // By default a verified account with another email is offered "Join as ..."; strict matching keeps the wrong-account page.
    $page->assertJsonPath('props.state', 'ready')->assertJsonPath('props.other_email', true)->assertJsonPath('props.signed_in_as', 'invitee+1@example.test');
    expect($page->json('props.invitation.email'))->toBe('invitee+2@example.test');
    config(['concierge.invitations.match_email' => true]);
    $this->get($other, firstRunInertia())->assertJsonPath('props.state', 'wrong_account');
    config(['concierge.invitations.match_email' => false]);

    $member = firstRunFork('already-member');
    $this->actingAs(User::query()->where('email', 'member+1@example.test')->firstOrFail());
    $this->get($member, firstRunInertia())->assertJsonPath('props.state', 'already_member')->assertJsonPath('props.title', "You're already in ".Workspace::query()->firstOrFail()->name);

    // Left: no workspace any more; the invitation is spent, and the person lands on the workspaces page until the no-workspace page exists.
    firstRunFork('left');
    $left = User::query()->where('email', 'left+1@example.test')->firstOrFail();
    expect(Workspace::query()->memberOf($left)->count())->toBe(0);
});
test('every credential the demo prints signs in', function () {
    expect(app(Kernel::class)->call('kit:first-run-demo', ['--fork' => 'all']))->toBe(0);
    $output = app(Kernel::class)->output();
    preg_match_all('/email: ([a-z]+\+\d+@example\.test)\n\s*password: (demo-[A-Za-z0-9]+)/', $output, $found, PREG_SET_ORDER);
    expect(count($found))->toBeGreaterThanOrEqual(1);
    foreach ($found as [, $email, $password]) {
        Auth::forgetGuards();
        $this->flushSession();
        $this->postJson('/login', ['email' => $email, 'password' => $password])->assertOk();
        $this->assertAuthenticated('web');
        expect(auth('web')->user()->email)->toBe($email);
    }
});
test('opening a pending invitation to a workspace you are already in closes it', function () {
    $workspace = Workspace::query()->firstOrFail();
    $owner = app(Concierge::class)->ownerOf($workspace)->user;
    $user = User::query()->forceCreate(['name' => 'Already In', 'email' => 'already.in@example.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
    $token = InviteMember::run($owner, $workspace, 'already.in@example.test', new GrantSet(['viewer']))->token;

    // They got in by another route while the invitation was still pending.
    ConciergeModels::membership()::make(['workspace_id' => $workspace->getKey(), 'user_id' => $user->getKey()])->forceFill(['status' => MembershipStatus::Active, 'joined_at' => now()])->save();
    $status = fn () => ConciergeModels::invitation()::query()->where('workspace_id', $workspace->getKey())->where('email', 'already.in@example.test')->value('status');
    expect($status()->value)->toBe('pending');

    $this->actingAs($user)->get('/invitations/'.$token, firstRunInertia())->assertOk()->assertJsonPath('props.state', 'already_member');
    expect($status()->value)->toBe('accepted');
});
test('someone who joined but skipped the welcome steps is sent through them before the dashboard', function () {
    $workspace = Workspace::query()->firstOrFail();
    expect(app(Kernel::class)->call('kit:first-run-demo', ['--fork' => 'already-member']))->toBe(0);
    $user = User::query()->where('email', 'member+1@example.test')->firstOrFail();
    $dashboard = WorkspaceUrl::to($workspace, '', 'session-ui', request());

    // Joined through an invitation, never saw the steps: the first dashboard visit sends them there, and the steps end back on the dashboard.
    $this->actingAs($user)->get($dashboard, firstRunInertia())->assertRedirect('/welcome');
    $this->get('/welcome')->assertRedirect('/welcome/timezone');
    $this->post('/welcome/timezone', ['timezone' => 'Europe/Amsterdam'])->assertRedirect('/welcome');
    $this->post('/welcome/photo/skip')->assertRedirect('/welcome');
    $this->get('/welcome')->assertRedirect();
    $page = $this->get($dashboard, firstRunInertia())->assertOk();
    $page->assertJsonPath('props.welcome.done', 1)->assertJsonPath('props.welcome.checklist.0.state', 'done');
});
test('the signed in forks print one click links that only work locally', function () {
    expect(app(Kernel::class)->call('kit:first-run-demo', ['--fork' => 'already-member']))->toBe(0);
    preg_match('#https?://\S+/demo-login/\d+\?\S+#', app(Kernel::class)->output(), $link);
    expect($link)->not->toBeEmpty();
    $url = $link[0];
    $member = User::query()->where('email', 'member+1@example.test')->firstOrFail();

    // A tampered link is refused; a good one signs in and goes on to the invitation.
    $this->get(str_replace('demo-login/'.$member->id, 'demo-login/1', $url))->assertForbidden();
    $this->assertGuest('web');
    $putenv = getenv('APP_ENV');
    try {
        putenv('APP_ENV=production');
        $this->app['env'] = 'production';
        $this->get($url)->assertNotFound();
        $this->assertGuest('web');
    } finally {
        putenv('APP_ENV='.$putenv);
        $this->app['env'] = $putenv;
    }
    putenv('APP_ENV=local');
    $this->app['env'] = 'local';
    $this->get($url)->assertRedirect();
    $this->assertAuthenticatedAs($member, 'web');
    putenv('APP_ENV='.$putenv);
});
test('a join as current fork prints a one click link to the join as page', function () {
    $path = firstRunFork('join-as-current');
    $this->assertNotSame('', $path);
    $this->actingAs(User::query()->where('email', 'current+1@example.test')->firstOrFail());
    $this->get($path, firstRunInertia())->assertOk()->assertJsonPath('props.state', 'ready')->assertJsonPath('props.other_email', true);
});
test('a verified account with another email can join as itself unless matching is strict', function () {
    $workspace = Workspace::query()->firstOrFail();
    $owner = app(Concierge::class)->ownerOf($workspace)->user;
    $current = User::query()->forceCreate(['name' => 'Current Person', 'email' => 'current.person@example.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
    $unverified = User::query()->forceCreate(['name' => 'Unconfirmed', 'email' => 'unconfirmed@example.test', 'password' => bcrypt('x')]);
    $token = InviteMember::run($owner, $workspace, 'sent.to@example.test', new GrantSet(['viewer']))->token;

    // The default: the page offers "Join as {current}", naming the address it was sent to.
    $page = $this->actingAs($current)->get('/invitations/'.$token, firstRunInertia())->assertOk();
    $page->assertJsonPath('props.state', 'ready')->assertJsonPath('props.other_email', true)->assertJsonPath('props.signed_in_as', 'current.person@example.test')->assertJsonPath('props.invitation.email', 'sent.to@example.test');

    // An unverified account is asked to verify first and cannot join.
    $this->actingAs($unverified)->get('/invitations/'.$token, firstRunInertia())->assertJsonPath('props.state', 'unverified')->assertJsonPath('props.verify_url', '/email/verify');
    $this->actingAs($unverified)->postJson('/invitations/'.$token)->assertStatus(409);

    // Strict mode keeps the old rule: only the invited address, Switch account only.
    config(['concierge.invitations.match_email' => true]);
    $this->actingAs($current)->get('/invitations/'.$token, firstRunInertia())->assertJsonPath('props.state', 'wrong_account')->assertJsonPath('props.other_email', false);
    $this->actingAs($current)->postJson('/invitations/'.$token)->assertStatus(409);
    config(['concierge.invitations.match_email' => false]);

    // Joining as the current account: they are in, and People says which address was invited.
    $this->actingAs($current)->postJson('/invitations/'.$token)->assertCreated()->assertJsonPath('outcome', 'joined');
    expect(ConciergeModels::invitation()::query()->where('email', 'sent.to@example.test')->value('accepted_with_other_email'))->toBeTrue();
    $this->actingAs($owner);
    $dashboard = WorkspaceUrl::to($workspace, 'settings/people', 'session-ui', request());
    $row = collect($this->get($dashboard, firstRunInertia())->assertOk()->json('props.table.rows'))->firstWhere('member.email', 'current.person@example.test');
    expect($row['member']['invited_as'])->toBe('sent.to@example.test');
});
test('the default is to allow it and the kit flag makes it strict', function () {
    expect(config('concierge.invitations.match_email'))->toBeFalse();
    $config = require dirname(__DIR__).'/config/concierge.php';
    expect($config['invitations']['match_email'])->toBeFalse();
});
test('a person with no workspace lands on the focus page and never gets a 403', function () {
    $workspace = Workspace::query()->firstOrFail();
    $owner = app(Concierge::class)->ownerOf($workspace)->user;
    expect(app(Kernel::class)->call('kit:first-run-demo', ['--fork' => 'left']))->toBe(0);
    $left = User::query()->where('email', 'left+1@example.test')->firstOrFail();
    expect(Workspace::query()->memberOf($left)->count())->toBe(0);

    // An invitation sent to them meanwhile.
    InviteMember::run($owner, $workspace, 'left+1@example.test', new GrantSet(['viewer']), 'Come back.');
    $this->actingAs($left);

    // Every way in lands there: the list, the old workspace's own address, the page itself. Nothing is a 403.
    $this->get('/me/workspaces', firstRunInertia())->assertRedirect('/no-workspace');
    $this->get('/no-workspace')->assertOk();
    $page = $this->get('/no-workspace', firstRunInertia())->assertOk();
    $page->assertJsonPath('component', 'Provision/NoWorkspace/Page')->assertJsonPath('props.shell.kind', 'focus');
    expect($page->json('props.explanation'))->toBe("You're not in a workspace yet. Ask your admin to invite left+1@example.test.");
    expect(Arr::only($page->json('props.invitations.0'), ['workspace', 'inviter']))->toBe(['workspace' => $workspace->name, 'inviter' => $owner->name]);
    expect($page->json('props.invitations.0.message'))->toBe('Come back.');

    // invite_only: no create action. Self-serve would offer one.
    expect($page->json('props.create'))->toBeNull();
    config(['kit.workspaces.provisioning' => 'self_serve']);
    expect($this->get('/no-workspace', firstRunInertia())->json('props.create.label'))->toBe('Create a workspace');
    config(['kit.workspaces.provisioning' => 'invite_only']);

    // The shell around the account pages has no workspace and no switcher, so it names the product where the switcher would be.
    $account = $this->get('/me/profile', firstRunInertia())->assertOk()->json('props.shell');
    expect($account['workspace'])->toBeNull();
    expect($account['switcher'])->toBeNull();
    expect($account['product']['name'])->toBe(config('app.name'));

    // A person with a workspace is sent to their list instead.
    $this->actingAs($owner);
    $this->get('/no-workspace')->assertRedirect('/me/workspaces');
});
test('the browsers zone is a validated cookie that refreshes until confirmed', function () {
    $workspace = Workspace::query()->firstOrFail();
    $person = User::query()->forceCreate(['name' => 'Zoned', 'email' => 'zoned@example.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
    $id = (int) $person->getKey();
    $url = WorkspaceUrl::to($workspace, '', 'session-ui', request());
    $get = fn (?string $zone) => $this->actingAs($person)->flushSession()->{$zone === null ? 'get' : 'withUnencryptedCookie'}(...($zone === null ? [$url] : ['tz', $zone]))->get($url);
    $state = fn () => UserState::get($id, 'timezone');

    // No cookie: nothing stored, the fallbacks answer (user, then workspace, then UTC).
    $this->actingAs($person)->get('/me/profile');
    expect($state())->toBeNull();
    expect(ClientTimezone::get(request(), null, null))->toBe('UTC');
    expect(ClientTimezone::get(request(), null, 'Europe/Paris'))->toBe('Europe/Paris');

    // Garbage and injection strings are ignored and never stored.
    foreach (['Mars/Base', "UTC'; DROP TABLE users;--", '../../etc/passwd', str_repeat('A', 300), ''] as $bad) {
        $this->flushSession();
        $this->actingAs($person)->withUnencryptedCookie('tz', $bad)->get('/me/profile')->assertOk();
        expect($state())->toBeNull('an invalid cookie is never stored: '.substr($bad, 0, 20));
    }

    // It is read raw (never encrypted), and a valid one refreshes an unconfirmed person.
    $this->flushSession();
    $this->actingAs($person)->withUnencryptedCookie('tz', 'Europe/Amsterdam')->get('/me/profile')->assertOk();
    expect($state())->toBe('Europe/Amsterdam');
    $this->flushSession();
    $this->actingAs($person)->withUnencryptedCookie('tz', 'Asia/Tokyo')->get('/me/profile');
    expect($state())->toBe('Asia/Tokyo');

    // No write when it has not changed.
    $writes = 0;
    DB::listen(function ($query) use (&$writes): void {
        if (str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, 'kit_user_state') || str_starts_with(strtolower($query->sql), 'update') && str_contains($query->sql, 'kit_user_state')) {
            $writes++;
        }
    });
    $this->flushSession();
    $this->actingAs($person)->withUnencryptedCookie('tz', 'Asia/Tokyo')->get('/me/profile');
    expect($writes)->toBe(0);

    // Confirmed, it is theirs: a different browser zone never overwrites it.
    UserState::set($id, 'timezone', 'Europe/London');
    UserState::set($id, 'timezone.confirmed');
    $this->flushSession();
    $this->actingAs($person)->withUnencryptedCookie('tz', 'Pacific/Auckland')->get('/me/profile');
    expect($state())->toBe('Europe/London');
});
test('the hidden field on the invitation forms saves a real zone and ignores the rest', function () {
    $workspace = Workspace::query()->firstOrFail();
    $owner = app(Concierge::class)->ownerOf($workspace)->user;
    $token = InviteMember::run($owner, $workspace, 'tz.signup@example.test', new GrantSet(['viewer']))->token;
    $this->post('/invitations/'.$token.'/account', ['name' => 'Tz Signup', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1', 'timezone' => 'America/Chicago'])->assertRedirect('/welcome');
    $id = (int) User::query()->where('email', 'tz.signup@example.test')->value('id');
    expect(UserState::get($id, 'timezone'))->toBe('America/Chicago');
    expect(UserState::has($id, 'timezone.confirmed'))->toBeFalse('a guess, not a confirmation');

    $existing = User::query()->forceCreate(['name' => 'Tz Accept', 'email' => 'tz.accept@example.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
    $accept = InviteMember::run($owner, $workspace, 'tz.accept@example.test', new GrantSet(['viewer']))->token;
    $this->actingAs($existing)->post('/invitations/'.$accept, ['timezone' => 'Mars/Base'])->assertRedirect();
    expect(UserState::get((int) $existing->getKey(), 'timezone'))->toBeNull('an invalid zone is ignored');
});
test('changing the timezone in the wizard saves it and continue confirms it', function () {
    $workspace = Workspace::query()->firstOrFail();
    $owner = app(Concierge::class)->ownerOf($workspace)->user;
    $token = InviteMember::run($owner, $workspace, 'wizard.tz@example.test', new GrantSet(['viewer']))->token;
    $this->post('/invitations/'.$token.'/account', ['name' => 'Wizard Tz', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'])->assertRedirect('/welcome');
    $id = (int) User::query()->where('email', 'wizard.tz@example.test')->value('id');

    $page = $this->get('/welcome/timezone', firstRunInertia())->assertOk();
    $page->assertJsonPath('props.save.url', '/welcome/timezone')->assertJsonPath('props.skip', null);

    // A change is saved at once and is still only a value, not a confirmation.
    $this->put('/welcome/timezone', ['timezone' => 'Europe/Berlin'])->assertRedirect();
    expect(UserState::get($id, 'timezone'))->toBe('Europe/Berlin');
    expect(Onboarding::pending($id, 'timezone') === false)->toBeFalse();
    $this->put('/welcome/timezone', ['timezone' => 'Nowhere/Land'])->assertSessionHasErrors('timezone');
    expect(UserState::get($id, 'timezone'))->toBe('Europe/Berlin');

    // Continue confirms it, and the step is done.
    $this->post('/welcome/timezone', ['timezone' => 'Europe/Berlin'])->assertRedirect('/welcome');
    expect(UserState::has($id, 'timezone.confirmed'))->toBeTrue();
    expect(Onboarding::state($id, 'timezone'))->toBe('done');
});
test('an owner confirms the workspace timezone and a member never sees the step', function () {
    $workspace = Workspace::query()->firstOrFail();
    $owner = app(Concierge::class)->ownerOf($workspace)->user;
    $member = User::query()->forceCreate(['name' => 'Plain Member', 'email' => 'plain.member@example.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
    AcceptInvitation::run($member, InviteMember::run($owner, $workspace, 'plain.member@example.test', new GrantSet(['viewer']))->token);
    $session = ['welcome.workspace' => $workspace->id];

    // The owner is asked, starting from their own zone, and a change is saved to the workspace; Continue records it once.
    UserState::set((int) $owner->getKey(), 'timezone', 'Europe/Paris');
    $this->actingAs($owner)->withSession($session)->get('/welcome/workspace-timezone', firstRunInertia())->assertOk()
        ->assertJsonPath('component', 'Welcome/WorkspaceTimezone/Page')->assertJsonPath('props.value', 'Europe/Paris');
    $this->put('/welcome/workspace-timezone', ['timezone' => 'Europe/Paris'])->assertRedirect();
    expect(app(TenantBoundary::class)->run($workspace->id, fn () => WorkspaceSettings::current()->timezone))->toBe('Europe/Paris');
    $this->post('/welcome/workspace-timezone', ['timezone' => 'Europe/Paris'])->assertRedirect('/welcome');
    expect(Onboarding::state((int) $owner->getKey(), 'workspace-timezone'))->toBe('done');

    // Once confirmed it does not repeat, for anyone.
    $this->actingAs($owner)->withSession($session)->get('/welcome/workspace-timezone', firstRunInertia())->assertNotFound();

    // A member is never asked: the step does not exist for them.
    DB::table('kit_user_state')->where('workspace_id', $workspace->id)->where('key', 'workspace.timezone.confirmed')->delete();
    $this->actingAs($member)->withSession($session)->get('/welcome/workspace-timezone', firstRunInertia())->assertNotFound();
    $this->actingAs($member)->withSession($session)->put('/welcome/workspace-timezone', ['timezone' => 'Asia/Tokyo'])->assertNotFound();
});
test('the accept page names the workspace the product the inviter and the personal message', function () {
    $workspace = Workspace::query()->firstOrFail();
    $owner = app(Concierge::class)->ownerOf($workspace)->user;
    $token = InviteMember::run($owner, $workspace, 'polish@example.test', new GrantSet(['viewer']), 'Looking forward to working with you.')->token;
    $page = $this->get('/invitations/'.$token, firstRunInertia())->assertOk();
    $page->assertJsonPath('props.title', 'Join '.$workspace->name.' on '.config('app.name'))->assertJsonPath('props.invitation.inviter', $owner->name)->assertJsonPath('props.invitation.message', 'Looking forward to working with you.')->assertJsonPath('props.state', 'register_required');

    // No message, no quote.
    $plain = InviteMember::run($owner, $workspace, 'plain@example.test', new GrantSet(['viewer']))->token;
    $this->get('/invitations/'.$plain, firstRunInertia())->assertJsonPath('props.invitation.message', null);
});
