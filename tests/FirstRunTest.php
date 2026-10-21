<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Concierge;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\UserState;
use Tey\Kit\Features\Workspaces\Models\Workspace;
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
        $page->assertJsonPath('props.state', 'wrong_account')->assertJsonPath('props.signed_in_as', 'invitee+1@example.test');
        $this->assertSame('invitee+2@example.test', $page->json('props.invitation.email'));

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
}
