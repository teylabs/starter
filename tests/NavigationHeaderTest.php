<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Kit\Features\Core\Data\NavigationHeader;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

function navigationHeaderInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function navigationHeaderSignIn(int $id): void
{
    Auth::forgetGuards();
    test()->password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make(test()->password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => test()->password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
function navigationHeaderSignOut(): void
{
    test()->postJson('/logout')->assertNoContent();
    test()->flushSession();
    test()->forgetCookies();
    Auth::forgetGuards();
}
test('account pages escape to the workspace and other pages have no strip unless declared', function () {
    require dirname(__DIR__).'/database/fixture.php';
    expect(app(Kernel::class)->call('kit:permissions:sync', ['--no-interaction' => true]))->toBe(0);
    navigationHeaderSignIn(1);
    $header = fn (string $url): ?array => $this->get($url, navigationHeaderInertia())->assertOk()->json('props.layout.navigationHeader');

    // Top level: the dashboard and the settings landing page sit directly under the home.
    expect($header('/@alpha/dashboard'))->toBeNull();
    expect($header('/@alpha/settings'))->toBeNull();

    // Settings sections have no strip; every /me page is an escape to the workspace the viewer was in; nothing is derived from the trail.
    foreach (['/@alpha/settings/general', '/@alpha/settings/people'] as $url) {
        expect($header($url))->toBeNull($url);
    }
    $workspace = Workspace::findOrFail(1)->name;
    foreach (['/me', '/me/profile', '/me/password', '/me/security'] as $url) {
        expect($header($url)['label'])->toBe('Back to '.$workspace, $url);
    }

    // The switcher names the workspace on an account page too: there is no account mode.
    $shell = $this->get('/me/profile', navigationHeaderInertia())->json('props.shell');
    expect($shell['workspace']['name'])->toBe(Workspace::findOrFail(1)->name);
    $this->assertArrayNotHasKey('mode', $shell);
});
test('a nested resource page declares its parent explicitly through its view model', function () {
    require dirname(__DIR__).'/database/fixture.php';
    navigationHeaderSignIn(1);
    $this->get('/@alpha/dashboard', navigationHeaderInertia())->assertOk();
    $declared = new class extends PageViewModel
    {
        public function title(): string
        {
            return 'A member';
        }

        protected function navigationHeader(): ?NavigationHeader
        {
            return new NavigationHeader('Back to People', '/settings/people');
        }
    };
    $plain = new class extends PageViewModel
    {
        public function title(): string
        {
            return 'A page';
        }
    };
    expect($declared->layout()->navigationHeader?->toArray())->toBe(['label' => 'Back to People', 'url' => '/settings/people']);
    expect($plain->layout()->navigationHeader)->toBeNull('nothing is derived: a page with no declaration has no strip');
});
