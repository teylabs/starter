<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require dirname(__DIR__).'/vendor/autoload.php';

/** Settings is one landing page that lists the sections providers register: workspace ones at /@{workspace}/settings, personal ones at /me. */
final class SettingsLandingTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.name' => 'Product name']);
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

    public function test_the_landing_page_lists_the_registered_sections_in_order(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $general = ['key' => 'general', 'label' => 'General', 'description' => 'The workspace display name and timezone.', 'icon' => 'sliders-horizontal', 'url' => '/settings/general'];
        $connections = ['key' => 'connections', 'label' => 'Connections', 'description' => 'Delegated access for MCP clients to this workspace.', 'icon' => 'plug', 'url' => '/settings/connections'];
        $this->get('/@alpha/settings', $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Sections/Index/Page')
            // One "Workspace" group for the workspace's sections (a group never repeats a lone item's label); each carries its lucide icon.
            ->assertJsonPath('props.groups', [['label' => 'Workspace', 'sections' => [$general, $connections]]]);
        // A provider registers a section with a group and an order, not a nav item; a lower order comes first, and a new group appears in place.
        app(Registry::class)->settingsSection('workspace', 'billing', 'Billing', 'Plan and invoices.', null, 'Account', -50, 'credit-card')->settingsSection('workspace', 'owner-only', 'Owner only', 'Needs write access.', 'settings.write', 'Workspace', 30);
        $this->get('/@alpha/settings', $this->inertia())->assertOk()
            ->assertJsonPath('props.groups.0.label', 'Account')->assertJsonPath('props.groups.0.sections.0.url', '/settings/billing')->assertJsonPath('props.groups.0.sections.0.icon', 'credit-card')
            ->assertJsonPath('props.groups.1.label', 'Workspace')->assertJsonPath('props.groups.1.sections.2.label', 'Owner only');
        // A section registered without an icon carries the generic one.
        app(Registry::class)->settingsSection('workspace', 'plain', 'Plain', 'No icon given.', null, 'Workspace', 40);
        $this->get('/@alpha/settings', $this->inertia())->assertJsonPath('props.groups.1.sections.3.icon', 'settings');
        // The registered sections are listed by what the user may open: a viewer cannot write settings.
        $this->postJson('/logout')->assertNoContent();
        $this->signIn(3);
        $this->get('/@alpha/settings', $this->inertia())->assertOk()
            ->assertJsonPath('props.groups.1.sections', [$general, $connections, ['key' => 'plain', 'label' => 'Plain', 'description' => 'No icon given.', 'icon' => 'settings', 'url' => '/settings/plain']])->assertJsonCount(2, 'props.groups');
    }

    public function test_each_section_has_its_own_page_in_both_url_forms(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        foreach (['/@alpha/settings/general', '/settings/general'] as $path) {
            $this->get($path, $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Settings/Edit/Page');
        }
        // The Connections section is a page of the same shape (its own tests set up the OAuth tables it reads).
        $this->assertTrue(Route::has('workspaces.connections.index') && Route::has('short.workspaces.connections.index'));
        $this->assertStringEndsWith('/@alpha/settings/connections', route('workspaces.connections.index', ['workspace' => 'alpha']));
        // Settings is one link in the nav, to the landing page; the sections are not.
        $this->get('/settings/general', $this->inertia())->assertJsonPath('props.layout.navigation.1', ['label' => 'Settings', 'url' => '/settings', 'current' => true, 'group' => 'workspace'])
            ->assertJsonPath('props.breadcrumbs', [['title' => 'Product name', 'url' => '/', 'is_current_page' => false], ['title' => 'Settings', 'url' => '/settings', 'is_current_page' => false], ['title' => 'General', 'url' => '/settings/general', 'is_current_page' => true]]);
        $this->get('/@alpha/settings', $this->inertia())->assertJsonPath('props.layout.navigation.1.current', true)->assertJsonPath('props.breadcrumbs', [['title' => 'Product name', 'url' => '/', 'is_current_page' => false], ['title' => 'Settings', 'url' => '/settings', 'is_current_page' => true]]);
    }

    public function test_the_personal_landing_page_lists_the_personal_sections(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->get('/me', $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Sections/Index/Page')
            ->assertJsonPath('props.groups', [['label' => 'Account', 'sections' => [['key' => 'workspaces', 'label' => 'Workspaces', 'description' => 'The workspaces you belong to.', 'icon' => 'building-2', 'url' => '/me/workspaces']]]])
            ->assertJsonPath('props.breadcrumbs', [['title' => 'Product name', 'url' => '/', 'is_current_page' => false], ['title' => 'Account', 'url' => '/me', 'is_current_page' => true]]);
        $this->getJson('/me')->assertOk()->assertJsonPath('sections.0.key', 'workspaces');
        $this->postJson('/logout')->assertNoContent();
        $this->get('/me')->assertRedirect('/login');
    }

    public function test_the_settings_dropdown_is_gone(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        // The shell carries no settings menu and the nav no settings slot.
        $this->get('/dashboard', $this->inertia())->assertOk()->assertJsonMissingPath('props.layout.settings');
        $this->assertFileDoesNotExist(base_path('resources/js/pages/Workspace/Partials/SettingsMenu.vue'));
        foreach (['Layout.vue', 'Partials/ShellDrawer.vue', 'Partials/PrimaryNav.vue'] as $file) {
            $this->assertStringNotContainsString('SettingsMenu', file_get_contents(base_path('resources/js/pages/Workspace/'.$file)), $file);
            $this->assertStringNotContainsString('layout.settings', file_get_contents(base_path('resources/js/pages/Workspace/'.$file)), $file);
        }
        $this->assertSame(['primary'], array_values(array_unique(array_column(app(Registry::class)->navigationLinks(), 'slot'))));
    }

    public function test_a_section_page_carries_the_sidebar_with_the_current_section_marked(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        // The sidebar is the same server data as the landing cards, plus which one is shown; the landing pages (the card grids) have none.
        foreach (['/settings/general', '/@alpha/settings/general'] as $path) {
            $nav = $this->get($path, $this->inertia())->assertOk()->json('props.settings_nav');
            $this->assertSame('Workspace', $nav['groups'][0]['label']);
            $this->assertSame([['general', true], ['connections', false]], array_map(fn (array $section): array => [$section['key'], $section['current']], $nav['groups'][0]['sections']));
            $this->assertSame('sliders-horizontal', $nav['groups'][0]['sections'][0]['icon']);
        }
        $this->get('/settings', $this->inertia())->assertJsonPath('props.settings_nav', null);
        $this->get('/dashboard', $this->inertia())->assertJsonPath('props.settings_nav', null);
        // A viewer's sidebar lists only what the viewer may open.
        app(Registry::class)->settingsSection('workspace', 'owner-only', 'Owner only', 'Needs write access.', 'settings.write', 'Workspace', 30);
        $keys = array_column($this->get('/settings/general', $this->inertia())->json('props.settings_nav.groups.0.sections'), 'key');
        $this->assertContains('owner-only', $keys);
        $this->postJson('/logout')->assertNoContent();
        $this->signIn(3);
        $this->get('/settings/connections', $this->inertia());
        $this->assertNotContains('owner-only', array_column($this->get('/settings/general', $this->inertia())->json('props.settings_nav.groups.0.sections') ?? [], 'key'));
    }
}
