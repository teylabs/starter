<?php

use App\Models\User;
use Glhd\Gretel\Registry;
use Glhd\Gretel\Support\Cache;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
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
    // The route cache check writes to its own file, never the app's; a parallel worker already points the variable at its own bootstrap directory, so the previous value comes back afterwards.
    $this->routeCache = sys_get_temp_dir().'/starter-routes-'.bin2hex(random_bytes(4)).'.php';
    $this->previousRouteCache = getenv('APP_ROUTES_CACHE') ?: null;
    putenv('APP_ROUTES_CACHE='.$this->routeCache);
    $_ENV['APP_ROUTES_CACHE'] = $_SERVER['APP_ROUTES_CACHE'] = $this->routeCache;
    config(['app.name' => 'Product name']);
});
afterEach(function () {
    if ($this->previousRouteCache === null) {
        putenv('APP_ROUTES_CACHE');
        unset($_ENV['APP_ROUTES_CACHE'], $_SERVER['APP_ROUTES_CACHE']);
    } else {
        putenv('APP_ROUTES_CACHE='.$this->previousRouteCache);
        $_ENV['APP_ROUTES_CACHE'] = $_SERVER['APP_ROUTES_CACHE'] = $this->previousRouteCache;
    }
    if ($this->routeCache !== null && is_file($this->routeCache)) {
        unlink($this->routeCache);
    }
});
function breadcrumbsInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function breadcrumbsSignIn(int $id): void
{
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
function breadcrumbsAddSecondWorkspace(): void
{
    $invite = InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']));
    AcceptInvitation::run(User::findOrFail(1), $invite->token);
}
test('the trail to a record page in both url forms', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    breadcrumbsSignIn(1);
    $home = ['label' => 'Product name', 'url' => '/', 'current' => false, 'home' => true];

    // One workspace: the crumbs link in the short form, whichever form the page was reached by.
    foreach (['/records/1', '/@alpha/records/1'] as $path) {
        $this->get($path, breadcrumbsInertia())->assertOk()->assertJsonPath('component', 'Workspace/Record/Show/Page')
            ->assertJsonPath('props.layout.breadcrumbs', [$home, ['label' => 'Records', 'url' => '/records', 'current' => false, 'home' => false], ['label' => 'Alpha private', 'url' => '/records/1', 'current' => true, 'home' => false]]);
    }

    // A list page is the home icon and itself.
    $this->get('/records', breadcrumbsInertia())->assertOk()->assertJsonPath('props.layout.breadcrumbs', [$home, ['label' => 'Records', 'url' => '/records', 'current' => true, 'home' => false]]);

    // The dashboard and settings are siblings under home, not nested.
    $this->get('/', breadcrumbsInertia())->assertOk()->assertJsonPath('props.layout.breadcrumbs', [[...$home, 'current' => true]]);
    $this->get('/settings', breadcrumbsInertia())->assertOk()->assertJsonPath('props.layout.breadcrumbs', [$home, ['label' => 'Settings', 'url' => '/settings', 'current' => true, 'home' => false]]);

    // Two workspaces: the same trail, in the full form; home is "/" and is named by the workspace.
    breadcrumbsAddSecondWorkspace();
    $alpha = ['label' => 'Alpha', 'url' => '/@alpha', 'current' => false, 'home' => true];
    $this->get('/@alpha/records/1', breadcrumbsInertia())->assertOk()
        ->assertJsonPath('props.layout.breadcrumbs', [$alpha, ['label' => 'Records', 'url' => '/@alpha/records', 'current' => false, 'home' => false], ['label' => 'Alpha private', 'url' => '/@alpha/records/1', 'current' => true, 'home' => false]]);
    $this->get('/records/1', breadcrumbsInertia())->assertOk()
        ->assertJsonPath('props.layout.breadcrumbs.0.url', '/@alpha')->assertJsonPath('props.layout.breadcrumbs.1.url', '/@alpha/records');

    // The personal page is rooted at home too, with no workspace in its own links.
    $this->get('/me/workspaces', breadcrumbsInertia())->assertOk()->assertJsonPath('props.layout.breadcrumbs', [$alpha, ['label' => 'Account', 'url' => '/me', 'current' => false, 'home' => false], ['label' => 'Workspaces', 'url' => '/me/workspaces', 'current' => true, 'home' => false]]);
});
test('every page route has a breadcrumb', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    $registry = app(Registry::class);
    $checked = [];
    foreach (Route::getRoutes() as $route) {
        $action = $route->getActionName();
        if (in_array('GET', $route->methods(), true) && (str_starts_with($action, 'Tey\\Kit\\Features\\') || str_starts_with($action, 'App\\') || str_starts_with($action, 'Tests\\Fixtures\\')) && ! str_contains($action, 'PeopleModalController') && ! str_contains($action, 'DemoLogin') && ! str_contains($action, 'Moved') && ! str_contains($action, 'PeopleModalController') && ! str_contains($action, 'AvatarImage')) {
            expect($registry->get($route))->not->toBeNull($route->uri().' declares no breadcrumb');
            $checked[] = $route->uri();
        }
    }

    // Every kit page, in both URL forms, and the personal one.
    foreach (['@{workspace}', '/', '@{workspace}/records', 'records', '@{workspace}/records/{record}', 'records/{record}', '@{workspace}/settings', 'settings', '@{workspace}/settings/general', 'settings/general', '@{workspace}/settings/connections', 'settings/connections', 'me', 'me/workspaces'] as $uri) {
        expect($checked)->toContain($uri);
    }
});
test('the current crumb is a real link to its own url', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    breadcrumbsSignIn(1);

    // The last crumb carries its own URL (a link, not plain text) and is marked current; the ancestors are not.
    foreach (['/', '/settings', '/settings/general', '/records', '/records/1', '/me'] as $path) {
        $crumbs = $this->get($path, breadcrumbsInertia())->assertOk()->json('props.layout.breadcrumbs');
        $last = end($crumbs);
        expect($last['current'])->toBeTrue($path);
        expect($last['url'])->toBe($path, $path.' links to itself');
        expect(array_filter(array_slice($crumbs, 0, -1), fn (array $crumb): bool => $crumb['current']))->toBe([], $path.' marks only the last crumb current');
    }

    // The component renders every crumb as a link, marks the current one with aria-current and the foreground colour, and ancestors stay muted.
    $vue = (string) file_get_contents(dirname(__DIR__).'/resources/js/pages/Workspace/Partials/Breadcrumbs.vue');
    $this->assertStringContainsString(":aria-current=\"crumb.current ? 'page' : undefined\"", $vue);
    $this->assertStringContainsString("crumb.current ? 'text-foreground' : ''", $vue);
    expect(substr_count($vue, '<Link '))->toBe(2, 'the home icon and the titled crumbs are both links');
    $this->assertStringNotContainsString('<span aria-current', $vue, 'the current crumb is not plain text');
});
test('every trail starts at home', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    $registry = app(Registry::class);
    $params = ['workspace' => '1', 'record' => '1', 'connection' => '1'];
    $checked = 0;
    foreach (Route::getRoutes() as $route) {
        $action = $route->getActionName();
        if (! in_array('GET', $route->methods(), true) || str_contains($action, 'PeopleModalController') || ! (str_starts_with($action, 'Tey\\Kit\\Features\\') || str_starts_with($action, 'App\\') || str_starts_with($action, 'Tests\\Fixtures\\')) || str_contains($action, 'Moved') || str_contains($action, 'AvatarImage') || str_contains($action, 'DemoLogin')) {
            continue;
        }
        $crumb = $registry->get($route);
        expect($crumb)->not->toBeNull($route->uri().' declares no breadcrumb');
        for ($guard = 0; $crumb->parent !== null && ($parent = $crumb->parent->resolve($params, $registry)) !== null && $guard < 8; $guard++) {
            $crumb = $parent;
        }
        expect($crumb->name)->toBe('home', $route->uri().' has a trail rooted at '.$crumb->name);
        $checked++;
    }
    expect($checked)->toBeGreaterThanOrEqual(11);
});
test('breadcrumbs and routes cache', function () {
    // Breadcrumbs first, then the routes, as the deploy does; each to a temporary file, never the app's.
    $crumbs = sys_get_temp_dir().'/starter-crumbs-'.bin2hex(random_bytes(4)).'.php';
    try {
        $this->app->instance(Cache::class, new Cache(app(Filesystem::class), $crumbs));
        expect(app(Kernel::class)->call('breadcrumbs:cache'))->toBe(0);
        expect($crumbs)->toBeFile();
        expect(app(Kernel::class)->call('route:cache'))->toBe(0);
        expect($this->routeCache)->toBeFile();
        $this->assertStringContainsString('@{workspace}/dashboard', (string) file_get_contents($this->routeCache));
    } finally {
        @unlink($crumbs);
    }
});
