<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

function setupInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function setupSignIn(int $id): void
{
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}

// Settings → Setup over the session, beside its MCP read (workspace_setup_read): an owner reads their workspace's setup, another workspace's
// is absent, and a viewer, who can't change settings, can't read it. A fresh local install shows nothing failing.
test('session setup', function () {
    require dirname(__DIR__).'/database/fixture.php';
    config(['app.env' => 'local']);
    setupSignIn(1);
    $response = $this->get('/@alpha/settings/setup', setupInertia())->assertOk()->assertJsonPath('component', 'Workspace/Settings/Setup/Page');
    $states = collect($response->json('props.rows'))->pluck('state', 'key');
    expect($states)->not->toBeEmpty();
    expect($states->filter(fn (string $state): bool => in_array($state, ['failing', 'missing'], true))->all())->toBe([], 'nothing is red on a fresh local install');
    $this->get('/@beta/settings/setup', setupInertia())->assertNotFound();

    $this->postJson('/logout')->assertNoContent();
    setupSignIn(3);
    expect($this->get('/@alpha/settings/setup', setupInertia())->status())->toBeIn([403, 404]);
});
