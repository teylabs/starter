<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/** Signs in through the form (not JSON) and returns where it redirects. */
function homeLandingSignIn(int $id): string
{
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);

    return test()->post('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertRedirect()->headers->get('Location');
}
test('a user with no workspace sees the chooser', function () {
    require dirname(__DIR__).'/database/fixture.php';
    expect(homeLandingSignIn(99))->toBe(url('/me/workspaces'));
});
test('a user with one workspace lands in it', function () {
    require dirname(__DIR__).'/database/fixture.php';
    expect(homeLandingSignIn(2))->toBe(url('/dashboard'));
});
test('a user with two workspaces keeps the chooser', function () {
    require dirname(__DIR__).'/database/fixture.php';
    $invite = InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), 'actor2@example.test', new GrantSet(['viewer']));
    AcceptInvitation::run(User::findOrFail(2), $invite->token);
    expect(homeLandingSignIn(2))->toBe(url('/me/workspaces'));
});
test('the workspace list opens a single workspace and lists several in the full shell', function () {
    require dirname(__DIR__).'/database/fixture.php';
    $inertia = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    homeLandingSignIn(2);
    $this->withCookie(config('session.cookie'), app('session')->getId());
    $this->get('/me/workspaces', $inertia)->assertRedirect('/dashboard');
    $invite = InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), 'actor2@example.test', new GrantSet(['viewer']));
    AcceptInvitation::run(User::findOrFail(2), $invite->token);
    $this->get('/me/workspaces', $inertia)->assertOk()->assertJsonPath('component', 'Workspace/Index/Page')
        ->assertJsonPath('props.shell.user.name', 'Beta')->assertJsonPath('props.shell.product.name', config('app.name'))
        ->assertJsonCount(2, 'props.shell.switcher.choices')->assertJsonPath('props.shell.switcher.choices.0.url', '/@alpha/dashboard');
});
