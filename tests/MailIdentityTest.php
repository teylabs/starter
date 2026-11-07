<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

function mailIdentityInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function mailIdentitySignIn(int $id): void
{
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
// The Email sending section over the session, beside its MCP read (workspace_mail_identity_read, in McpTransportTest): the owner reads the
// workspace's sender identity, another workspace's is absent.
test('session mail identity', function () {
    require dirname(__DIR__).'/database/fixture.php';
    mailIdentitySignIn(1);
    $this->get('/@alpha/settings/email', mailIdentityInertia())->assertOk()->assertJsonPath('component', 'Workspace/Settings/Email/Page');
    $this->get('/@beta/settings/email', mailIdentityInertia())->assertNotFound();
});
