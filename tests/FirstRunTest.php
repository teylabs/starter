<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase;
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

require dirname(__DIR__).'/vendor/autoload.php';

/** The first-run path of an invited person: join with the account the invitation is for, the welcome steps in the FocusShell, then the dashboard's greeting and welcome card. */
final class FirstRunTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app['env'] = 'local';
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        $this->assertSame(0, app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]));
    }

    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    public function test_an_invitee_signs_up_walks_the_welcome_steps_and_lands_on_the_dashboard_with_the_welcome_card(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $owner = app(Concierge::class)->ownerOf($workspace)->user;
        $token = InviteMember::run($owner, $workspace, 'new.person@example.test', new GrantSet(['viewer']))->token;

        // One step: the account the invitation is for is created and the invitation accepted, straight into the welcome steps.
        $this->post('/invitations/'.$token.'/account', ['name' => 'Nora Newcomer', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'])->assertRedirect('/welcome');
        $this->assertSame(1, User::query()->where('email', 'new.person@example.test')->count());

        $this->get('/welcome')->assertRedirect('/welcome/timezone');
        $timezone = $this->get('/welcome/timezone', $this->inertia())->assertOk();
        $timezone->assertJsonPath('component', 'Welcome/Timezone/Page')->assertJsonPath('props.shell.kind', 'focus')
            ->assertJsonPath('props.shell.step', ['current' => 1, 'total' => 2, 'label' => 'Your timezone']);
        $this->post('/welcome/timezone', ['timezone' => 'Not/AZone'])->assertSessionHasErrors('timezone');
        $this->post('/welcome/timezone', ['timezone' => 'Europe/Amsterdam'])->assertRedirect('/welcome');

        $this->get('/welcome', $this->inertia())->assertRedirect('/welcome/photo');
        $this->get('/welcome/photo', $this->inertia())->assertOk()->assertJsonPath('component', 'Welcome/Photo/Page')->assertJsonPath('props.shell.step.current', 2);
        // Skipping never blocks: the flow ends on the dashboard of the workspace they joined.
        $this->post('/welcome/photo/skip')->assertRedirect('/welcome');
        $this->get('/welcome')->assertRedirect(url(WorkspaceUrl::to($workspace, 'dashboard', 'session-ui', request())));

        $dashboard = $this->get(WorkspaceUrl::to($workspace, 'dashboard', 'session-ui', request()), $this->inertia())->assertOk();
        $this->assertStringStartsWith('Good ', $dashboard->json('props.greeting'));
        $this->assertStringEndsWith(', Nora', $dashboard->json('props.greeting'));
        $this->assertTrue(UserState::has((int) User::query()->where('email', 'new.person@example.test')->value('id'), 'onboarding.done'));
        $dashboard->assertJsonPath('props.welcome.title', 'Welcome to '.config('app.name').', Nora.')
            ->assertJsonPath('props.welcome.body', 'Your workspace for '.$workspace->name.' is ready.')
            ->assertJsonPath('props.welcome.checklist', [['id' => 'timezone', 'label' => 'Your timezone', 'hint' => 'Confirm the timezone your dates and times show in', 'url' => '/welcome/timezone', 'state' => 'done'], ['id' => 'photo', 'label' => 'Your photo', 'hint' => 'Add a photo so teammates know who you are', 'url' => '/welcome/photo', 'state' => 'skipped']])
            ->assertJsonPath('props.welcome.done', 1)->assertJsonPath('props.welcome.total', 2)
            ->assertJsonPath('props.welcome.dismiss.label', 'Dismiss welcome')
            ->assertJsonPath('props.welcome.dismiss.url', '/me/welcome/'.$workspace->id);

        // Dismissing it removes the card for good, on every device.
        $this->delete('/me/welcome/'.$workspace->id)->assertRedirect();
        $after = $this->get(WorkspaceUrl::to($workspace, 'dashboard', 'session-ui', request()), $this->inertia())->assertOk();
        $after->assertJsonPath('props.welcome', null);
    }

    public function test_the_demo_command_prints_a_fresh_link_each_time_and_only_runs_locally(): void
    {
        $this->assertSame(0, app(Kernel::class)->call('kit:first-run-demo'));
        $first = app(Kernel::class)->output();
        $this->assertSame(0, app(Kernel::class)->call('kit:first-run-demo'));
        $second = app(Kernel::class)->output();
        $this->assertStringContainsString('demo+1@example.test', $first);
        $this->assertStringContainsString('demo+2@example.test', $second);
        $this->assertStringContainsString('/invitations/', $second);
        $this->app['env'] = 'production';
        $this->assertSame(1, app(Kernel::class)->call('kit:first-run-demo'));
    }

    /** the invitation path in the command's output for a fork */
    private function fork(string $name): string
    {
        $this->assertSame(0, app(Kernel::class)->call('kit:first-run-demo', ['--fork' => $name]));
        $output = app(Kernel::class)->output();
        // A one-click sign-in link carries the invitation it opens as its `to`.
        if (preg_match('#https?://\S+/demo-login/\d+\?(\S+)#', $output, $signed)) {
            parse_str($signed[1], $query);

            return $query['to'];
        }
        preg_match('#/invitations/[A-Za-z0-9_-]+#', $output, $match);

        return $match[0] ?? '';
    }

    public function test_every_invitation_fork_renders_its_own_state(): void
    {
        $state = fn (string $path): ?string => $this->get($path, $this->inertia())->assertOk()->json('props.state');
        $this->assertSame('register_required', $state($this->fork('new')));
        $this->assertSame('sign_in_required', $state($this->fork('existing-signed-out')));
        $this->assertSame('expired', $state($this->fork('expired')));
        $this->assertSame('revoked', $state($this->fork('revoked')));
        $this->assertSame('invalid', $state($this->fork('rotated')));

        $invitee = $this->fork('signed-in-as-invitee');
        $this->actingAs(User::query()->where('email', 'invitee+1@example.test')->firstOrFail());
        $this->assertSame('ready', $state($invitee));
        $this->get($invitee, $this->inertia())->assertJsonPath('props.shell.kind', 'focus');

        $other = $this->fork('signed-in-as-other');
        $page = $this->get($other, $this->inertia())->assertOk();
        // By default a verified account with another email is offered "Join as ..."; strict matching keeps the wrong-account page.
        $page->assertJsonPath('props.state', 'ready')->assertJsonPath('props.other_email', true)->assertJsonPath('props.signed_in_as', 'invitee+1@example.test');
        $this->assertSame('invitee+2@example.test', $page->json('props.invitation.email'));
        config(['concierge.invitations.match_email' => true]);
        $this->get($other, $this->inertia())->assertJsonPath('props.state', 'wrong_account');
        config(['concierge.invitations.match_email' => false]);

        $member = $this->fork('already-member');
        $this->actingAs(User::query()->where('email', 'member+1@example.test')->firstOrFail());
        $this->get($member, $this->inertia())->assertJsonPath('props.state', 'already_member')->assertJsonPath('props.title', "You're already in ".Workspace::query()->firstOrFail()->name);

        // Left: no workspace any more; the invitation is spent, and the person lands on the workspaces page until the no-workspace page exists.
        $this->fork('left');
        $left = User::query()->where('email', 'left+1@example.test')->firstOrFail();
        $this->assertSame(0, Workspace::query()->memberOf($left)->count());
    }

    public function test_every_credential_the_demo_prints_signs_in(): void
    {
        $this->assertSame(0, app(Kernel::class)->call('kit:first-run-demo', ['--fork' => 'all']));
        $output = app(Kernel::class)->output();
        preg_match_all('/email: ([a-z]+\+\d+@example\.test)\n\s*password: (demo-[A-Za-z0-9]+)/', $output, $found, PREG_SET_ORDER);
        $this->assertGreaterThanOrEqual(1, count($found));
        foreach ($found as [, $email, $password]) {
            Auth::forgetGuards();
            $this->flushSession();
            $this->postJson('/login', ['email' => $email, 'password' => $password])->assertOk();
            $this->assertAuthenticated('web');
            $this->assertSame($email, auth('web')->user()->email);
        }
    }

    public function test_opening_a_pending_invitation_to_a_workspace_you_are_already_in_closes_it(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $owner = app(Concierge::class)->ownerOf($workspace)->user;
        $user = User::query()->forceCreate(['name' => 'Already In', 'email' => 'already.in@example.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
        $token = InviteMember::run($owner, $workspace, 'already.in@example.test', new GrantSet(['viewer']))->token;
        // They got in by another route while the invitation was still pending.
        ConciergeModels::membership()::make(['workspace_id' => $workspace->getKey(), 'user_id' => $user->getKey()])->forceFill(['status' => MembershipStatus::Active, 'joined_at' => now()])->save();
        $status = fn () => ConciergeModels::invitation()::query()->where('workspace_id', $workspace->getKey())->where('email', 'already.in@example.test')->value('status');
        $this->assertSame('pending', $status()->value);

        $this->actingAs($user)->get('/invitations/'.$token, $this->inertia())->assertOk()->assertJsonPath('props.state', 'already_member');
        $this->assertSame('accepted', $status()->value);
    }

    public function test_someone_who_joined_but_skipped_the_welcome_steps_is_sent_through_them_before_the_dashboard(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $this->assertSame(0, app(Kernel::class)->call('kit:first-run-demo', ['--fork' => 'already-member']));
        $user = User::query()->where('email', 'member+1@example.test')->firstOrFail();
        $dashboard = WorkspaceUrl::to($workspace, 'dashboard', 'session-ui', request());

        // Joined through an invitation, never saw the steps: the first dashboard visit sends them there, and the steps end back on the dashboard.
        $this->actingAs($user)->get($dashboard, $this->inertia())->assertRedirect('/welcome');
        $this->get('/welcome')->assertRedirect('/welcome/timezone');
        $this->post('/welcome/timezone', ['timezone' => 'Europe/Amsterdam'])->assertRedirect('/welcome');
        $this->post('/welcome/photo/skip')->assertRedirect('/welcome');
        $this->get('/welcome')->assertRedirect();
        $page = $this->get($dashboard, $this->inertia())->assertOk();
        $page->assertJsonPath('props.welcome.done', 1)->assertJsonPath('props.welcome.checklist.0.state', 'done');
    }

    public function test_the_signed_in_forks_print_one_click_links_that_only_work_locally(): void
    {
        $this->assertSame(0, app(Kernel::class)->call('kit:first-run-demo', ['--fork' => 'already-member']));
        preg_match('#https?://\S+/demo-login/\d+\?\S+#', app(Kernel::class)->output(), $link);
        $this->assertNotEmpty($link);
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
    }

    public function test_a_join_as_current_fork_prints_a_one_click_link_to_the_join_as_page(): void
    {
        $path = $this->fork('join-as-current');
        $this->assertNotSame('', $path);
        $this->actingAs(User::query()->where('email', 'current+1@example.test')->firstOrFail());
        $this->get($path, $this->inertia())->assertOk()->assertJsonPath('props.state', 'ready')->assertJsonPath('props.other_email', true);
    }

    public function test_a_verified_account_with_another_email_can_join_as_itself_unless_matching_is_strict(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $owner = app(Concierge::class)->ownerOf($workspace)->user;
        $current = User::query()->forceCreate(['name' => 'Current Person', 'email' => 'current.person@example.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
        $unverified = User::query()->forceCreate(['name' => 'Unconfirmed', 'email' => 'unconfirmed@example.test', 'password' => bcrypt('x')]);
        $token = InviteMember::run($owner, $workspace, 'sent.to@example.test', new GrantSet(['viewer']))->token;

        // The default: the page offers "Join as {current}", naming the address it was sent to.
        $page = $this->actingAs($current)->get('/invitations/'.$token, $this->inertia())->assertOk();
        $page->assertJsonPath('props.state', 'ready')->assertJsonPath('props.other_email', true)->assertJsonPath('props.signed_in_as', 'current.person@example.test')->assertJsonPath('props.invitation.email', 'sent.to@example.test');

        // An unverified account is asked to verify first and cannot join.
        $this->actingAs($unverified)->get('/invitations/'.$token, $this->inertia())->assertJsonPath('props.state', 'unverified')->assertJsonPath('props.verify_url', '/email/verify');
        $this->actingAs($unverified)->postJson('/invitations/'.$token)->assertStatus(409);

        // Strict mode keeps the old rule: only the invited address, Switch account only.
        config(['concierge.invitations.match_email' => true]);
        $this->actingAs($current)->get('/invitations/'.$token, $this->inertia())->assertJsonPath('props.state', 'wrong_account')->assertJsonPath('props.other_email', false);
        $this->actingAs($current)->postJson('/invitations/'.$token)->assertStatus(409);
        config(['concierge.invitations.match_email' => false]);

        // Joining as the current account: they are in, and People says which address was invited.
        $this->actingAs($current)->postJson('/invitations/'.$token)->assertCreated()->assertJsonPath('outcome', 'joined');
        $this->assertTrue(ConciergeModels::invitation()::query()->where('email', 'sent.to@example.test')->value('accepted_with_other_email'));
        $this->actingAs($owner);
        $dashboard = WorkspaceUrl::to($workspace, 'settings/people', 'session-ui', request());
        $row = collect($this->get($dashboard, $this->inertia())->assertOk()->json('props.table.rows'))->firstWhere('member.email', 'current.person@example.test');
        $this->assertSame('sent.to@example.test', $row['member']['invited_as']);
    }

    public function test_the_default_is_to_allow_it_and_the_kit_flag_makes_it_strict(): void
    {
        $this->assertFalse(config('concierge.invitations.match_email'));
        $config = require dirname(__DIR__).'/config/concierge.php';
        $this->assertFalse($config['invitations']['match_email']);
    }

    public function test_a_person_with_no_workspace_lands_on_the_focus_page_and_never_gets_a_403(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $owner = app(Concierge::class)->ownerOf($workspace)->user;
        $this->assertSame(0, app(Kernel::class)->call('kit:first-run-demo', ['--fork' => 'left']));
        $left = User::query()->where('email', 'left+1@example.test')->firstOrFail();
        $this->assertSame(0, Workspace::query()->memberOf($left)->count());
        // An invitation sent to them meanwhile.
        InviteMember::run($owner, $workspace, 'left+1@example.test', new GrantSet(['viewer']), 'Come back.');
        $this->actingAs($left);

        // Every way in lands there: the list, the old workspace's own address, the page itself. Nothing is a 403.
        $this->get('/me/workspaces', $this->inertia())->assertRedirect('/no-workspace');
        $this->get('/no-workspace')->assertOk();
        $page = $this->get('/no-workspace', $this->inertia())->assertOk();
        $page->assertJsonPath('component', 'Provision/NoWorkspace/Page')->assertJsonPath('props.shell.kind', 'focus');
        $this->assertSame("You're not in a workspace yet. Ask your admin to invite left+1@example.test.", $page->json('props.explanation'));
        $this->assertSame(['workspace' => $workspace->name, 'inviter' => $owner->name], Arr::only($page->json('props.invitations.0'), ['workspace', 'inviter']));
        $this->assertSame('Come back.', $page->json('props.invitations.0.message'));
        // invite_only: no create action. Self-serve would offer one.
        $this->assertNull($page->json('props.create'));
        config(['kit.workspaces.provisioning' => 'self_serve']);
        $this->assertSame('Create a workspace', $this->get('/no-workspace', $this->inertia())->json('props.create.label'));
        config(['kit.workspaces.provisioning' => 'invite_only']);
        // The shell around the account pages has no workspace and no switcher, so it names the product where the switcher would be.
        $account = $this->get('/me/profile', $this->inertia())->assertOk()->json('props.shell');
        $this->assertNull($account['workspace']);
        $this->assertNull($account['switcher']);
        $this->assertSame(config('app.name'), $account['product']['name']);
        // A person with a workspace is sent to their list instead.
        $this->actingAs($owner);
        $this->get('/no-workspace')->assertRedirect('/me/workspaces');
    }

    public function test_the_browsers_zone_is_a_validated_cookie_that_refreshes_until_confirmed(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $person = User::query()->forceCreate(['name' => 'Zoned', 'email' => 'zoned@example.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
        $id = (int) $person->getKey();
        $url = WorkspaceUrl::to($workspace, 'dashboard', 'session-ui', request());
        $get = fn (?string $zone) => $this->actingAs($person)->flushSession()->{$zone === null ? 'get' : 'withUnencryptedCookie'}(...($zone === null ? [$url] : ['tz', $zone]))->get($url);
        $state = fn () => UserState::get($id, 'timezone');

        // No cookie: nothing stored, the fallbacks answer (user, then workspace, then UTC).
        $this->actingAs($person)->get('/me/profile');
        $this->assertNull($state());
        $this->assertSame('UTC', ClientTimezone::get(request(), null, null));
        $this->assertSame('Europe/Paris', ClientTimezone::get(request(), null, 'Europe/Paris'));
        // Garbage and injection strings are ignored and never stored.
        foreach (['Mars/Base', "UTC'; DROP TABLE users;--", '../../etc/passwd', str_repeat('A', 300), ''] as $bad) {
            $this->flushSession();
            $this->actingAs($person)->withUnencryptedCookie('tz', $bad)->get('/me/profile')->assertOk();
            $this->assertNull($state(), 'an invalid cookie is never stored: '.substr($bad, 0, 20));
        }
        // It is read raw (never encrypted), and a valid one refreshes an unconfirmed person.
        $this->flushSession();
        $this->actingAs($person)->withUnencryptedCookie('tz', 'Europe/Amsterdam')->get('/me/profile')->assertOk();
        $this->assertSame('Europe/Amsterdam', $state());
        $this->flushSession();
        $this->actingAs($person)->withUnencryptedCookie('tz', 'Asia/Tokyo')->get('/me/profile');
        $this->assertSame('Asia/Tokyo', $state());
        // No write when it has not changed.
        $writes = 0;
        DB::listen(function ($query) use (&$writes): void {
            if (str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, 'kit_user_state') || str_starts_with(strtolower($query->sql), 'update') && str_contains($query->sql, 'kit_user_state')) {
                $writes++;
            }
        });
        $this->flushSession();
        $this->actingAs($person)->withUnencryptedCookie('tz', 'Asia/Tokyo')->get('/me/profile');
        $this->assertSame(0, $writes);
        // Confirmed, it is theirs: a different browser zone never overwrites it.
        UserState::set($id, 'timezone', 'Europe/London');
        UserState::set($id, 'timezone.confirmed');
        $this->flushSession();
        $this->actingAs($person)->withUnencryptedCookie('tz', 'Pacific/Auckland')->get('/me/profile');
        $this->assertSame('Europe/London', $state());
    }

    public function test_the_hidden_field_on_the_invitation_forms_saves_a_real_zone_and_ignores_the_rest(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $owner = app(Concierge::class)->ownerOf($workspace)->user;
        $token = InviteMember::run($owner, $workspace, 'tz.signup@example.test', new GrantSet(['viewer']))->token;
        $this->post('/invitations/'.$token.'/account', ['name' => 'Tz Signup', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1', 'timezone' => 'America/Chicago'])->assertRedirect('/welcome');
        $id = (int) User::query()->where('email', 'tz.signup@example.test')->value('id');
        $this->assertSame('America/Chicago', UserState::get($id, 'timezone'));
        $this->assertFalse(UserState::has($id, 'timezone.confirmed'), 'a guess, not a confirmation');

        $existing = User::query()->forceCreate(['name' => 'Tz Accept', 'email' => 'tz.accept@example.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
        $accept = InviteMember::run($owner, $workspace, 'tz.accept@example.test', new GrantSet(['viewer']))->token;
        $this->actingAs($existing)->post('/invitations/'.$accept, ['timezone' => 'Mars/Base'])->assertRedirect();
        $this->assertNull(UserState::get((int) $existing->getKey(), 'timezone'), 'an invalid zone is ignored');
    }

    public function test_changing_the_timezone_in_the_wizard_saves_it_and_continue_confirms_it(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $owner = app(Concierge::class)->ownerOf($workspace)->user;
        $token = InviteMember::run($owner, $workspace, 'wizard.tz@example.test', new GrantSet(['viewer']))->token;
        $this->post('/invitations/'.$token.'/account', ['name' => 'Wizard Tz', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'])->assertRedirect('/welcome');
        $id = (int) User::query()->where('email', 'wizard.tz@example.test')->value('id');

        $page = $this->get('/welcome/timezone', $this->inertia())->assertOk();
        $page->assertJsonPath('props.save.url', '/welcome/timezone')->assertJsonPath('props.skip', null);
        // A change is saved at once and is still only a value, not a confirmation.
        $this->put('/welcome/timezone', ['timezone' => 'Europe/Berlin'])->assertRedirect();
        $this->assertSame('Europe/Berlin', UserState::get($id, 'timezone'));
        $this->assertFalse(Onboarding::pending($id, 'timezone') === false);
        $this->put('/welcome/timezone', ['timezone' => 'Nowhere/Land'])->assertSessionHasErrors('timezone');
        $this->assertSame('Europe/Berlin', UserState::get($id, 'timezone'));
        // Continue confirms it, and the step is done.
        $this->post('/welcome/timezone', ['timezone' => 'Europe/Berlin'])->assertRedirect('/welcome');
        $this->assertTrue(UserState::has($id, 'timezone.confirmed'));
        $this->assertSame('done', Onboarding::state($id, 'timezone'));
    }

    public function test_an_owner_confirms_the_workspace_timezone_and_a_member_never_sees_the_step(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $owner = app(Concierge::class)->ownerOf($workspace)->user;
        $member = User::query()->forceCreate(['name' => 'Plain Member', 'email' => 'plain.member@example.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
        AcceptInvitation::run($member, InviteMember::run($owner, $workspace, 'plain.member@example.test', new GrantSet(['viewer']))->token);
        $session = ['welcome.workspace' => $workspace->id];

        // The owner is asked, starting from their own zone, and a change is saved to the workspace; Continue records it once.
        UserState::set((int) $owner->getKey(), 'timezone', 'Europe/Paris');
        $this->actingAs($owner)->withSession($session)->get('/welcome/workspace-timezone', $this->inertia())->assertOk()
            ->assertJsonPath('component', 'Welcome/WorkspaceTimezone/Page')->assertJsonPath('props.value', 'Europe/Paris');
        $this->put('/welcome/workspace-timezone', ['timezone' => 'Europe/Paris'])->assertRedirect();
        $this->assertSame('Europe/Paris', app(TenantBoundary::class)->run($workspace->id, fn () => WorkspaceSettings::current()->timezone));
        $this->post('/welcome/workspace-timezone', ['timezone' => 'Europe/Paris'])->assertRedirect('/welcome');
        $this->assertSame('done', Onboarding::state((int) $owner->getKey(), 'workspace-timezone'));
        // Once confirmed it does not repeat, for anyone.
        $this->actingAs($owner)->withSession($session)->get('/welcome/workspace-timezone', $this->inertia())->assertNotFound();

        // A member is never asked: the step does not exist for them.
        DB::table('kit_user_state')->where('workspace_id', $workspace->id)->where('key', 'workspace.timezone.confirmed')->delete();
        $this->actingAs($member)->withSession($session)->get('/welcome/workspace-timezone', $this->inertia())->assertNotFound();
        $this->actingAs($member)->withSession($session)->put('/welcome/workspace-timezone', ['timezone' => 'Asia/Tokyo'])->assertNotFound();
    }

    public function test_the_accept_page_names_the_workspace_the_product_the_inviter_and_the_personal_message(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $owner = app(Concierge::class)->ownerOf($workspace)->user;
        $token = InviteMember::run($owner, $workspace, 'polish@example.test', new GrantSet(['viewer']), 'Looking forward to working with you.')->token;
        $page = $this->get('/invitations/'.$token, $this->inertia())->assertOk();
        $page->assertJsonPath('props.title', 'Join '.$workspace->name.' on '.config('app.name'))->assertJsonPath('props.invitation.inviter', $owner->name)->assertJsonPath('props.invitation.message', 'Looking forward to working with you.')->assertJsonPath('props.state', 'register_required');
        // No message, no quote.
        $plain = InviteMember::run($owner, $workspace, 'plain@example.test', new GrantSet(['viewer']))->token;
        $this->get('/invitations/'.$plain, $this->inertia())->assertJsonPath('props.invitation.message', null);
    }
}
