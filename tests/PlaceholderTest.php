<?php

use App\Models\User;
use Illuminate\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\AssertsPlaceholder;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\PlaceholderRoutes;

require_once __DIR__.'/Concerns/AssertsPlaceholder.php';
uses(AssertsPlaceholder::class);

beforeEach(function () {
    config(['app.name' => 'Product name']);
});
function placeholderInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function placeholderSignIn(int $id): void
{
    Auth::forgetGuards();
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
function placeholderRegister(): void
{
    $registry = app(Registry::class);
    $registry->placeholder('orders.index', 'orders', 'dashboard.read', 'Orders', 'Every order with its status and next step.', ['Orders'], 'full', 'the Orders release', [['label' => 'Back to the dashboard', 'route' => 'workspaces.dashboard.index'], ['label' => 'Manage people', 'route' => 'workspaces.people.index', 'ability' => 'members.manage']]);
    $registry->placeholder('billing.index', 'billing', 'members.manage', 'Billing', 'Plans and invoices.', ['Billing'], 'md');
    app(PlaceholderRoutes::class)->register();
    app('router')->getRoutes()->refreshNameLookups();
}
test('a member with the ability gets the honest page in the real pages shell', function () {
    require dirname(__DIR__).'/database/fixture.php';
    placeholderSignIn(3);
    placeholderRegister();
    foreach (['/@alpha/orders', '/orders'] as $path) {
        $page = $this->get($path, placeholderInertia())->assertOk();
        $page->assertJsonPath('component', 'Workspace/Placeholder/Page')->assertJsonPath('props.title', 'Orders')->assertJsonPath('props.description', 'Every order with its status and next step.')
            ->assertJsonPath('props.milestone', 'the Orders release')->assertJsonPath('props.width', 'full')->assertJsonPath('props.shell.kind', 'app')
            ->assertJsonPath('props.layout.documentTitle', 'Orders · Product name');
        expect(array_slice(array_column($page->json('props.layout.breadcrumbs'), 'label'), -1))->toBe(['Orders']);
        // Links are descriptors, and the one to a page this viewer may not open is simply not there.
        expect(array_column($page->json('props.links'), 'label'))->toBe(['Back to the dashboard']);
    }
    $this->assertPlaceholder('orders.index', '/@alpha/orders', placeholderInertia());
    $this->assertPlaceholder('short.orders.index', '/orders', placeholderInertia());
});
test('without the ability it is a 404 like a stranger and a guest signs in first', function () {
    require dirname(__DIR__).'/database/fixture.php';
    placeholderRegister();
    $this->get('/@alpha/billing')->assertRedirect('/login');

    // A viewer lacks the billing ability: 404, not 403.
    placeholderSignIn(3);
    $this->get('/@alpha/billing', placeholderInertia())->assertNotFound();
    $this->get('/billing', placeholderInertia())->assertNotFound();

    // A person who belongs to no workspace gets the same 404 and learns nothing.
    $stranger = User::create(['id' => 77, 'name' => 'Stranger', 'email' => 'actor77@example.test', 'email_verified_at' => now()]);
    expect($stranger)->not->toBeNull();
    $this->postJson('/logout')->assertNoContent();
    $this->flushSession();
    placeholderSignIn(77);
    $this->get('/@alpha/orders', placeholderInertia())->assertNotFound();

    // The owner (who may manage billing) gets the page, with its own width.
    $this->postJson('/logout')->assertNoContent();
    $this->flushSession();
    placeholderSignIn(1);
    $this->get('/@alpha/billing', placeholderInertia())->assertOk()->assertJsonPath('props.width', 'md')->assertJsonPath('props.milestone', null);
});
test('a placeholder that shares a route name with a real page or breaks the rules is refused', function () {
    require dirname(__DIR__).'/database/fixture.php';
    $registry = app(Registry::class);
    $registry->placeholder('workspaces.dashboard.index', 'forgotten', null, 'Dashboard', 'The real page landed and the line stayed.');
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('shares its route name');
    app(PlaceholderRoutes::class)->register();
});
test('the registry refuses a version a date more than three links and a twin', function () {
    $registry = app(Registry::class);
    foreach ([['the 1.2 release', 'version'], ['Q4 2026', 'date']] as [$milestone, $what]) {
        try {
            $registry->placeholder('x.'.$what, 'x-'.$what, null, 'X', 'x', [], null, $milestone);
            $this->fail('a milestone with a '.$what.' is refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('words only', $e->getMessage());
        }
    }
    $link = ['label' => 'a', 'route' => 'workspaces.dashboard.index'];
    try {
        $registry->placeholder('x.links', 'x-links', null, 'X', 'x', [], null, null, [$link, $link, $link, $link]);
        $this->fail('four links are refused');
    } catch (RuntimeException $e) {
        $this->assertStringContainsString('at most three', $e->getMessage());
    }
    $registry->placeholder('x.twice', 'x-twice', null, 'X', 'x');
    $this->expectExceptionMessage('registered twice');
    $registry->placeholder('x.twice', 'x-twice-again', null, 'X', 'x');
});
test('a planned settings section is tagged listed only for who may open it and renders in the settings layout', function () {
    require dirname(__DIR__).'/database/fixture.php';
    app(Registry::class)->settingsSection('workspace', 'billing', 'Billing', 'Plans and invoices.', 'members.manage', 'Workspace', 30, 'credit-card', placeholder: true);
    app(PlaceholderRoutes::class)->register();
    app('router')->getRoutes()->refreshNameLookups();
    placeholderSignIn(1);
    $sections = collect($this->get('/@alpha/settings', placeholderInertia())->assertOk()->json('props.groups'))->flatMap(fn ($group) => $group['sections']);
    expect($sections->firstWhere('key', 'billing')['badge'])->toBe('Planned');
    expect($sections->firstWhere('key', 'general')['badge'])->toBeNull();
    $page = $this->get('/@alpha/settings/billing', placeholderInertia())->assertOk();
    $page->assertJsonPath('component', 'Workspace/Settings/Placeholder/Page')->assertJsonPath('props.width', null);
    $nav = collect($page->json('props.layout.navigation'))->flatMap(fn ($group) => $group['items']);
    expect($nav->firstWhere('label', 'Billing')['badge'])->toBe('Planned');
    $this->assertPlaceholder('workspaces.settings.billing', '/@alpha/settings/billing', placeholderInertia());

    // A viewer may not open it, so it is not listed and its address is a 404.
    $this->postJson('/logout')->assertNoContent();
    $this->flushSession();
    placeholderSignIn(3);
    $listed = collect($this->get('/@alpha/settings', placeholderInertia())->json('props.groups'))->flatMap(fn ($group) => $group['sections'])->pluck('key')->all();
    expect($listed)->not->toContain('billing');
    $this->get('/@alpha/settings/billing', placeholderInertia())->assertNotFound();
});
test('two applications booted in one process each get their own placeholder routes', function () {
    // What was declared is remembered per application, not per PHP process: a later boot in the same process (a test suite, a worker) loses nothing.
    $first = $this->app;
    $second = require __DIR__.'/application.php';
    $second->make(Kernel::class)->bootstrap();
    $this->assertNotSame($first->make(PlaceholderRoutes::class), $second->make(PlaceholderRoutes::class));
    try {
        foreach ([$first, $second] as $app) {
            Container::setInstance($app);
            Facade::setFacadeApplication($app);
            $app->make(Registry::class)->placeholder('orders.index', 'orders', null, 'Orders', 'Every order with its status and next step.');
            $app->make(PlaceholderRoutes::class)->register();
            $app['router']->getRoutes()->refreshNameLookups();
            expect($app['router']->getRoutes()->getByName('orders.index'))->not->toBeNull('the full form is declared in each application');
            expect($app['router']->getRoutes()->getByName('short.orders.index'))->not->toBeNull('and the short form');
        }
    } finally {
        Container::setInstance($first);
        Facade::setFacadeApplication($first);
    }
});
