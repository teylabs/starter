<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';

final class HomeLandingTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** Signs in through the form (not JSON) and returns where it redirects. */
    private function signIn(int $id): string
    {
        $password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($password)]);

        return $this->post('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertRedirect()->headers->get('Location');
    }

    public function test_a_user_with_no_workspace_sees_the_chooser(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(url('/me/workspaces'), $this->signIn(99));
    }

    public function test_a_user_with_one_workspace_lands_in_it(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(url('/notes'), $this->signIn(2));
    }

    public function test_a_user_with_two_workspaces_keeps_the_chooser(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $invite = InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), 'actor2@example.test', new GrantSet(['viewer']));
        AcceptInvitation::run(User::findOrFail(2), $invite->token);
        $this->assertSame(url('/me/workspaces'), $this->signIn(2));
    }

    public function test_the_workspace_list_opens_a_single_workspace_and_lists_several_in_the_full_shell(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $inertia = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
        $this->signIn(2);
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->get('/me/workspaces', $inertia)->assertRedirect('/notes');
        $invite = InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), 'actor2@example.test', new GrantSet(['viewer']));
        AcceptInvitation::run(User::findOrFail(2), $invite->token);
        $this->get('/me/workspaces', $inertia)->assertOk()->assertJsonPath('component', 'Workspace/Index/Page')
            ->assertJsonPath('props.layout.user.name', 'Beta')->assertJsonPath('props.layout.product.name', config('app.name'))
            ->assertJsonCount(2, 'props.layout.switcher.choices')->assertJsonPath('props.layout.switcher.choices.0.url', '/@alpha/notes');
    }
}
