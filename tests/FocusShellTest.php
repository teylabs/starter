<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tey\Kit\Features\SessionUi\FocusStep;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\PageShell;

function focusShellInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function focusShellSignIn(int $id): void
{
    Auth::forgetGuards();
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
test('each viewer gets the shell the server picks and only its fields', function () {
    require dirname(__DIR__).'/database/fixture.php';
    expect(app(Kernel::class)->call('kit:permissions:sync', ['--no-interaction' => true]))->toBe(0);
    $token = str_repeat('a', 40);

    // A guest on an invitation link: the guest shell.
    $guest = $this->get('/invitations/'.$token, focusShellInertia())->assertOk()->json('props.shell');
    expect($guest['kind'])->toBe('guest');
    expect(array_keys(Arr::sortRecursive($guest)))->toBe(['footer', 'kind', 'product']);

    focusShellSignIn(1);

    // The same link for a signed-in viewer: the FocusShell, and nothing of the app shell or the guest shell.
    $focus = $this->get('/invitations/'.$token, focusShellInertia())->assertOk()->json('props.shell');
    expect($focus['kind'])->toBe('focus');
    expect(array_keys(Arr::sortRecursive($focus)))->toBe(['kind', 'links', 'product', 'step', 'user']);
    expect($focus['links'])->toBe(['sign_out' => '/logout', 'switch_account' => '/switch-account']);
    expect(array_keys(Arr::sortRecursive($focus['user'])))->toBe(['avatar_url', 'avatar_url_2x', 'email', 'initials', 'name']);
    expect($focus['user']['email'])->toBe('actor1@example.test');
    expect($focus['step'])->toBeNull();
    expect($focus['product']['name'])->toBe(config('app.name'));
    foreach (['switcher', 'navigation', 'workspace', 'footer', 'account', 'breadcrumbs'] as $appOnly) {
        $this->assertArrayNotHasKey($appOnly, $focus);
    }

    // A page in a workspace stays the app shell.
    $app = $this->get('/me/profile', focusShellInertia())->assertOk()->json('props.shell');
    expect($app['kind'])->toBe('app');
    expect($app)->toHaveKey('navigation');
});
test('a focus page reports its step and switch account ends the session', function () {
    require dirname(__DIR__).'/database/fixture.php';
    Route::middleware(['web', 'auth:web'])->get('/focus-probe', function (Request $request) {
        FocusStep::set($request, 2, 3, 'Your photo');

        return response()->json(app(PageShell::class)->forRequest($request)->toArray());
    })->name('focus.probe');
    focusShellSignIn(1);
    $shell = $this->getJson('/focus-probe')->assertOk()->json();
    expect($shell['kind'])->toBe('focus');
    expect($shell['step'])->toBe(['current' => 2, 'total' => 3, 'label' => 'Your photo']);

    $this->post('/switch-account', [], ['referer' => url('/invitations/'.str_repeat('a', 40))])->assertRedirect('/login');
    $this->assertGuest('web');
    expect(session('url.intended'))->toBe(url('/invitations/'.str_repeat('a', 40)));
});
