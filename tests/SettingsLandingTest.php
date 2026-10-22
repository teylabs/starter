<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

beforeEach(function () {
    config(['app.name' => 'Product name']);
});
function settingsLandingInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function settingsLandingSignIn(int $id): void
{
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
test('the landing page lists the registered sections in order', function () {
    require dirname(__DIR__).'/database/fixture.php';
    settingsLandingSignIn(1);
    $general = ['key' => 'general', 'label' => 'General', 'description' => 'The workspace display name and timezone.', 'icon' => 'sliders-horizontal', 'badge' => null, 'url' => '/settings/general'];
    $people = ['key' => 'people', 'label' => 'People', 'description' => 'Who belongs to this workspace, their roles, and who is invited.', 'icon' => 'users', 'badge' => null, 'url' => '/settings/people'];
    $this->get('/@alpha/settings', settingsLandingInertia())->assertOk()->assertJsonPath('component', 'Workspace/Sections/Index/Page')
        // One "Workspace" group for the workspace's sections (a group never repeats a lone item's label); each carries its lucide icon.
        ->assertJsonPath('props.groups', [['label' => 'Workspace', 'sections' => [$general, $people]]]);

    // A provider registers a section with a group and an order, not a nav item; a lower order comes first, and a new group appears in place.
    app(Registry::class)->settingsSection('workspace', 'billing', 'Billing', 'Plan and invoices.', null, 'Account', -50, 'credit-card')->settingsSection('workspace', 'owner-only', 'Owner only', 'Needs write access.', 'settings.write', 'Workspace', 30);
    $this->get('/@alpha/settings', settingsLandingInertia())->assertOk()
        ->assertJsonPath('props.groups.0.label', 'Account')->assertJsonPath('props.groups.0.sections.0.url', '/settings/billing')->assertJsonPath('props.groups.0.sections.0.icon', 'credit-card')
        ->assertJsonPath('props.groups.1.label', 'Workspace')->assertJsonPath('props.groups.1.sections.2.label', 'Owner only');

    // A section registered without an icon carries the generic one.
    app(Registry::class)->settingsSection('workspace', 'plain', 'Plain', 'No icon given.', null, 'Workspace', 40);
    $this->get('/@alpha/settings', settingsLandingInertia())->assertJsonPath('props.groups.1.sections.3.icon', 'settings');

    // The registered sections are listed by what the user may open: a viewer cannot write settings.
    $this->postJson('/logout')->assertNoContent();
    settingsLandingSignIn(3);
    $this->get('/@alpha/settings', settingsLandingInertia())->assertOk()
        ->assertJsonPath('props.groups.1.sections', [$general, ['key' => 'plain', 'label' => 'Plain', 'description' => 'No icon given.', 'icon' => 'settings', 'badge' => null, 'url' => '/settings/plain']])->assertJsonCount(2, 'props.groups');
});
test('each section has its own page in both url forms', function () {
    require dirname(__DIR__).'/database/fixture.php';
    settingsLandingSignIn(1);
    foreach (['/@alpha/settings/general', '/settings/general'] as $path) {
        $this->get($path, settingsLandingInertia())->assertOk()->assertJsonPath('component', 'Workspace/Settings/General/Page');
    }

    // The AI assistants page is hidden until the connection rework (see McpTransportTest).
    // The AI assistants page keeps its routes (it is only hidden: a 404 until kit.mcp.ui is on).
    expect(Route::has('workspaces.connections.index') && Route::has('short.workspaces.connections.index'))->toBeTrue();
    expect(route('workspaces.connections.index', ['workspace' => 'alpha']))->toEndWith('/@alpha/settings/connections');

    // Settings is one link in the nav, to the landing page; the sections are not.
    $this->get('/settings/general', settingsLandingInertia())->assertJsonPath('props.shell.navigation.1', ['label' => 'Settings', 'url' => '/settings', 'current' => true, 'group' => 'workspace'])
        ->assertJsonPath('props.layout.breadcrumbs', [['label' => 'Product name', 'url' => '/', 'current' => false, 'home' => true], ['label' => 'Settings', 'url' => '/settings', 'current' => false, 'home' => false], ['label' => 'General', 'url' => '/settings/general', 'current' => true, 'home' => false]]);
    $this->get('/@alpha/settings', settingsLandingInertia())->assertJsonPath('props.shell.navigation.1.current', true)->assertJsonPath('props.layout.breadcrumbs', [['label' => 'Product name', 'url' => '/', 'current' => false, 'home' => true], ['label' => 'Settings', 'url' => '/settings', 'current' => true, 'home' => false]]);
});
test('the personal landing page lists the personal sections', function () {
    require dirname(__DIR__).'/database/fixture.php';
    settingsLandingSignIn(1);
    $this->get('/me', settingsLandingInertia())->assertOk()->assertJsonPath('component', 'Workspace/Sections/Index/Page')
        ->assertJsonPath('props.groups', [['label' => 'Account', 'sections' => [['key' => 'profile', 'label' => 'Profile', 'description' => 'Your name, email address and photo.', 'icon' => 'user', 'badge' => null, 'url' => '/me/profile'], ['key' => 'password', 'label' => 'Password', 'description' => 'Change your password.', 'icon' => 'key-round', 'badge' => null, 'url' => '/me/password'], ['key' => 'security', 'label' => 'Security', 'description' => 'Two-factor authentication, signed-in devices and your security log.', 'icon' => 'shield-check', 'badge' => null, 'url' => '/me/security'], ['key' => 'workspaces', 'label' => 'Workspaces', 'description' => 'The workspaces you belong to.', 'icon' => 'building-2', 'badge' => null, 'url' => '/me/workspaces']]]])
        ->assertJsonPath('props.layout.breadcrumbs', [['label' => 'Product name', 'url' => '/', 'current' => false, 'home' => true], ['label' => 'Account', 'url' => '/me', 'current' => true, 'home' => false]]);
    $this->getJson('/me')->assertOk()->assertJsonPath('sections.3.key', 'workspaces');
    $this->postJson('/logout')->assertNoContent();
    $this->get('/me')->assertRedirect('/login');
});
test('the settings dropdown is gone', function () {
    require dirname(__DIR__).'/database/fixture.php';
    settingsLandingSignIn(1);

    // The shell carries no settings menu and the nav no settings slot.
    $this->get('/dashboard', settingsLandingInertia())->assertOk()->assertJsonMissingPath('props.layout.settings');
    $this->assertFileDoesNotExist(base_path('resources/js/pages/Workspace/Partials/SettingsMenu.vue'));
    foreach (['Layout.vue', 'Partials/ShellDrawer.vue', 'Partials/PrimaryNav.vue'] as $file) {
        $this->assertStringNotContainsString('SettingsMenu', file_get_contents(base_path('resources/js/pages/Workspace/'.$file)), $file);
        $this->assertStringNotContainsString('layout.settings', file_get_contents(base_path('resources/js/pages/Workspace/'.$file)), $file);
    }
    expect(array_values(array_unique(array_column(app(Registry::class)->navigationLinks(), 'slot'))))->toBe(['primary']);
});
test('a section page carries the sidebar with the current section marked', function () {
    require dirname(__DIR__).'/database/fixture.php';
    settingsLandingSignIn(1);

    // The section navigation is the page's `layout.navigation`: the same server data as the landing cards, with the shown one current. The landing pages (the
    // card grids) and the dashboard have none, and the empty list clears what the previous page showed.
    foreach (['/settings/general', '/@alpha/settings/general'] as $path) {
        $nav = $this->get($path, settingsLandingInertia())->assertOk()->json('props.layout.navigation');
        expect($nav)->toHaveCount(1);
        expect($nav[0]['label'])->toBe('Workspace');
        expect(array_map(fn (array $item): array => [$item['label'], $item['current']], $nav[0]['items']))->toBe([['General', true], ['People', false]]);
        expect($nav[0]['items'][0]['icon'])->toBe('sliders-horizontal');
    }
    $this->get('/settings', settingsLandingInertia())->assertJsonPath('props.layout.navigation', []);
    $this->get('/dashboard', settingsLandingInertia())->assertJsonPath('props.layout.navigation', []);

    // A viewer's navigation lists only what the viewer may open.
    app(Registry::class)->settingsSection('workspace', 'owner-only', 'Owner only', 'Needs write access.', 'settings.write', 'Workspace', 30);
    $labels = array_column($this->get('/settings/general', settingsLandingInertia())->json('props.layout.navigation.0.items'), 'label');
    expect($labels)->toContain('Owner only');
    $this->postJson('/logout')->assertNoContent();
    settingsLandingSignIn(3);
    expect(array_column($this->get('/settings/general', settingsLandingInertia())->json('props.layout.navigation.0.items') ?? [], 'label'))->not->toContain('Owner only');
});
