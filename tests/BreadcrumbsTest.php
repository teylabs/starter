<?php

use App\Models\User;
use Glhd\Gretel\Registry;
use Glhd\Gretel\Support\Cache;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
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

require dirname(__DIR__).'/vendor/autoload.php';

/** Breadcrumbs are the kit's own: a trail from the registered pages and nav, in the URL form the session uses, filtered by what the user may open. */
final class BreadcrumbsTest extends TestCase
{
    private ?string $routeCache = null;

    public function createApplication()
    {
        // The kit ships no sample resource: this test adopts the test-only Records fixture.
        require_once __DIR__.'/records_fixture.php';

        return starterAppWithRecords();
    }

    protected function setUp(): void
    {
        // The route cache check writes to its own file, never the app's.
        $this->routeCache = sys_get_temp_dir().'/starter-routes-'.bin2hex(random_bytes(4)).'.php';
        putenv('APP_ROUTES_CACHE='.$this->routeCache);
        $_ENV['APP_ROUTES_CACHE'] = $this->routeCache;
        parent::setUp();
        $this->withoutVite();
        config(['app.name' => 'Product name']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('APP_ROUTES_CACHE');
        unset($_ENV['APP_ROUTES_CACHE']);
        if ($this->routeCache !== null && is_file($this->routeCache)) {
            unlink($this->routeCache);
        }
    }

    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    private function signIn(int $id): void
    {
        $password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    }

    private function addSecondWorkspace(): void
    {
        $invite = InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']));
        AcceptInvitation::run(User::findOrFail(1), $invite->token);
    }

    public function test_the_trail_to_a_record_page_in_both_url_forms(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $this->signIn(1);
        $home = ['label' => 'Product name', 'url' => '/', 'current' => false, 'home' => true];
        // One workspace: the crumbs link in the short form, whichever form the page was reached by.
        foreach (['/records/1', '/@alpha/records/1'] as $path) {
            $this->get($path, $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Record/Show/Page')
                ->assertJsonPath('props.layout.breadcrumbs', [$home, ['label' => 'Records', 'url' => '/records', 'current' => false, 'home' => false], ['label' => 'Alpha private', 'url' => '/records/1', 'current' => true, 'home' => false]]);
        }
        // A list page is the home icon and itself.
        $this->get('/records', $this->inertia())->assertOk()->assertJsonPath('props.layout.breadcrumbs', [$home, ['label' => 'Records', 'url' => '/records', 'current' => true, 'home' => false]]);
        // The dashboard and settings are siblings under home, not nested.
        $this->get('/dashboard', $this->inertia())->assertOk()->assertJsonPath('props.layout.breadcrumbs', [$home, ['label' => 'Dashboard', 'url' => '/dashboard', 'current' => true, 'home' => false]]);
        $this->get('/settings', $this->inertia())->assertOk()->assertJsonPath('props.layout.breadcrumbs', [$home, ['label' => 'Settings', 'url' => '/settings', 'current' => true, 'home' => false]]);
        // Two workspaces: the same trail, in the full form; home is "/" and is named by the workspace.
        $this->addSecondWorkspace();
        $alpha = ['label' => 'Alpha', 'url' => '/', 'current' => false, 'home' => true];
        $this->get('/@alpha/records/1', $this->inertia())->assertOk()
            ->assertJsonPath('props.layout.breadcrumbs', [$alpha, ['label' => 'Records', 'url' => '/@alpha/records', 'current' => false, 'home' => false], ['label' => 'Alpha private', 'url' => '/@alpha/records/1', 'current' => true, 'home' => false]]);
        $this->get('/records/1', $this->inertia())->assertOk()
            ->assertJsonPath('props.layout.breadcrumbs.0.url', '/')->assertJsonPath('props.layout.breadcrumbs.1.url', '/@alpha/records');
        // The personal page is rooted at home too, with no workspace in its own links.
        $this->get('/me/workspaces', $this->inertia())->assertOk()->assertJsonPath('props.layout.breadcrumbs', [$alpha, ['label' => 'Account', 'url' => '/me', 'current' => false, 'home' => false], ['label' => 'Workspaces', 'url' => '/me/workspaces', 'current' => true, 'home' => false]]);
    }

    public function test_every_page_route_has_a_breadcrumb(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $registry = app(Registry::class);
        $checked = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (in_array('GET', $route->methods(), true) && (str_starts_with($action, 'Tey\\Kit\\Features\\') || str_starts_with($action, 'App\\') || str_starts_with($action, 'Tests\\Fixtures\\')) && ! str_contains($action, 'PeopleModalController') && ! str_contains($action, 'DemoLogin') && ! str_contains($action, 'Moved') && ! str_contains($action, 'PeopleModalController') && ! str_contains($action, 'AvatarImage')) {
                $this->assertNotNull($registry->get($route), $route->uri().' declares no breadcrumb');
                $checked[] = $route->uri();
            }
        }
        // Every kit page, in both URL forms, and the personal one.
        foreach (['@{workspace}/dashboard', 'dashboard', '@{workspace}/records', 'records', '@{workspace}/records/{record}', 'records/{record}', '@{workspace}/settings', 'settings', '@{workspace}/settings/general', 'settings/general', '@{workspace}/settings/connections', 'settings/connections', 'me', 'me/workspaces'] as $uri) {
            $this->assertContains($uri, $checked);
        }
    }

    public function test_the_current_crumb_is_a_real_link_to_its_own_url(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $this->signIn(1);
        // The last crumb carries its own URL (a link, not plain text) and is marked current; the ancestors are not.
        foreach (['/dashboard', '/settings', '/settings/general', '/records', '/records/1', '/me'] as $path) {
            $crumbs = $this->get($path, $this->inertia())->assertOk()->json('props.layout.breadcrumbs');
            $last = end($crumbs);
            $this->assertTrue($last['current'], $path);
            $this->assertSame($path, $last['url'], $path.' links to itself');
            $this->assertSame([], array_filter(array_slice($crumbs, 0, -1), fn (array $crumb): bool => $crumb['current']), $path.' marks only the last crumb current');
        }
        // The component renders every crumb as a link, marks the current one with aria-current and the foreground colour, and ancestors stay muted.
        $vue = (string) file_get_contents(dirname(__DIR__).'/resources/js/pages/Workspace/Partials/Breadcrumbs.vue');
        $this->assertStringContainsString(":aria-current=\"crumb.current ? 'page' : undefined\"", $vue);
        $this->assertStringContainsString("crumb.current ? 'text-foreground' : ''", $vue);
        $this->assertSame(2, substr_count($vue, '<Link '), 'the home icon and the titled crumbs are both links');
        $this->assertStringNotContainsString('<span aria-current', $vue, 'the current crumb is not plain text');
    }

    public function test_every_trail_starts_at_home(): void
    {
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
            $this->assertNotNull($crumb, $route->uri().' declares no breadcrumb');
            for ($guard = 0; $crumb->parent !== null && ($parent = $crumb->parent->resolve($params, $registry)) !== null && $guard < 8; $guard++) {
                $crumb = $parent;
            }
            $this->assertSame('home', $crumb->name, $route->uri().' has a trail rooted at '.$crumb->name);
            $checked++;
        }
        $this->assertGreaterThanOrEqual(11, $checked);
    }

    public function test_breadcrumbs_and_routes_cache(): void
    {
        // Breadcrumbs first, then the routes, as the deploy does; each to a temporary file, never the app's.
        $crumbs = sys_get_temp_dir().'/starter-crumbs-'.bin2hex(random_bytes(4)).'.php';
        try {
            $this->app->instance(Cache::class, new Cache(app(Filesystem::class), $crumbs));
            $this->assertSame(0, app(Kernel::class)->call('breadcrumbs:cache'));
            $this->assertFileExists($crumbs);
            $this->assertSame(0, app(Kernel::class)->call('route:cache'));
            $this->assertFileExists($this->routeCache);
            $this->assertStringContainsString('@{workspace}/dashboard', (string) file_get_contents($this->routeCache));
        } finally {
            @unlink($crumbs);
        }
    }
}
