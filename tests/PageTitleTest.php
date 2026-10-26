<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Fixtures\RecordsFixtureProvider;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

beforeEach(function () {
    config(['app.name' => 'Product']);
});
function pageTitleInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function pageTitleSignIn(int $id): void
{
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
test('titles are the breadcrumb label and the product', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    $this->get('/login')->assertOk()->assertSee('<title inertia>Sign in · Product</title>', false);
    pageTitleSignIn(1);
    foreach (['/' => 'Product', '/settings' => 'Settings · Product', '/records' => 'Records · Product', '/me' => 'Account · Product', '/settings/general' => 'General · Settings · Product', '/records/1' => 'Alpha private · Records · Product'] as $path => $title) {
        // The prop, for client-side visits, and the server-rendered <title>, so the first paint has it and no URL flashes.
        $this->get($path, pageTitleInertia())->assertOk()->assertJsonPath('props.layout.documentTitle', $title);
        $this->get($path)->assertOk()->assertSee('<title inertia>'.e($title).'</title>', false);
    }

    // With several workspaces the workspace's name is added, before the product.
    $invite = InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']));
    AcceptInvitation::run(User::findOrFail(1), $invite->token);
    $this->get('/@alpha/records', pageTitleInertia())->assertJsonPath('props.layout.documentTitle', 'Records · Alpha · Product');
    $this->get('/@alpha/settings/general', pageTitleInertia())->assertJsonPath('props.layout.documentTitle', 'General · Settings · Alpha · Product');

    // An error names its page once: the heading and the title are the same label.
    $this->get('/@nobody/records', pageTitleInertia())->assertNotFound()->assertJsonPath('props.layout.documentTitle', 'Page not found · Product')->assertJsonPath('props.title', 'Page not found');
});
test('no page has a blank or url title', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    pageTitleSignIn(1);
    $checked = 0;
    foreach (Route::getRoutes() as $route) {
        $action = $route->getActionName();
        if (! in_array('GET', $route->methods(), true) || ! (str_starts_with($action, 'Tey\\Kit\\Features\\') || str_starts_with($action, 'Tests\\Fixtures\\')) || str_contains($action, 'Moved') || str_contains($route->uri(), '{') && ! str_contains($route->uri(), '{workspace}') && ! str_contains($route->uri(), '{record}')) {
            continue;
        }
        $path = '/'.preg_replace(['#\{workspace\}#', '#\{record\}#'], ['alpha', '1'], str_replace('@{workspace}', '@alpha', $route->uri()));
        $response = $this->get($path, pageTitleInertia());
        if ($response->isRedirect() || $response->getStatusCode() >= 500 || ! str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            continue;
        }
        $title = (string) $response->json('props.layout.documentTitle');
        $this->assertNotSame('', trim($title), $path.' has a blank title');
        $this->assertDoesNotMatchRegularExpression('#^(https?:|/)#', $title, $path.' has a URL for a title');
        expect($title)->toEndWith('Product', $path);
        $checked++;
    }
    expect($checked)->toBeGreaterThanOrEqual(6);
});
