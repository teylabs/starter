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
        $people = ['key' => 'people', 'label' => 'People', 'description' => 'Who belongs to this workspace, their roles, and who is invited.', 'icon' => 'users', 'url' => '/settings/people'];
        $connections = ['key' => 'connections', 'label' => 'Connections', 'description' => 'Delegated access for MCP clients to this workspace.', 'icon' => 'plug', 'url' => '/settings/connections'];
        $this->get('/@alpha/settings', $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Sections/Index/Page')
            // One "Workspace" group for the workspace's sections (a group never repeats a lone item's label); each carries its lucide icon.
            ->assertJsonPath('props.groups', [['label' => 'Workspace', 'sections' => [$general, $people, $connections]]]);
        // A provider registers a section with a group and an order, not a nav item; a lower order comes first, and a new group appears in place.
        app(Registry::class)->settingsSection('workspace', 'billing', 'Billing', 'Plan and invoices.', null, 'Account', -50, 'credit-card')->settingsSection('workspace', 'owner-only', 'Owner only', 'Needs write access.', 'settings.write', 'Workspace', 30);
        $this->get('/@alpha/settings', $this->inertia())->assertOk()
            ->assertJsonPath('props.groups.0.label', 'Account')->assertJsonPath('props.groups.0.sections.0.url', '/settings/billing')->assertJsonPath('props.groups.0.sections.0.icon', 'credit-card')
            ->assertJsonPath('props.groups.1.label', 'Workspace')->assertJsonPath('props.groups.1.sections.3.label', 'Owner only');
        // A section registered without an icon carries the generic one.
        app(Registry::class)->settingsSection('workspace', 'plain', 'Plain', 'No icon given.', null, 'Workspace', 40);
        $this->get('/@alpha/settings', $this->inertia())->assertJsonPath('props.groups.1.sections.4.icon', 'settings');
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
            $this->get($path, $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Settings/General/Page');
        }
        // The Connections section is a page of the same shape (its own tests set up the OAuth tables it reads).
        $this->assertTrue(Route::has('workspaces.connections.index') && Route::has('short.workspaces.connections.index'));
        $this->assertStringEndsWith('/@alpha/settings/connections', route('workspaces.connections.index', ['workspace' => 'alpha']));
        // Settings is one link in the nav, to the landing page; the sections are not.
        $this->get('/settings/general', $this->inertia())->assertJsonPath('props.shell.navigation.1', ['label' => 'Settings', 'url' => '/settings', 'current' => true, 'group' => 'workspace'])
            ->assertJsonPath('props.layout.breadcrumbs', [['label' => 'Product name', 'url' => '/', 'current' => false], ['label' => 'Settings', 'url' => '/settings', 'current' => false], ['label' => 'General', 'url' => '/settings/general', 'current' => true]]);
        $this->get('/@alpha/settings', $this->inertia())->assertJsonPath('props.shell.navigation.1.current', true)->assertJsonPath('props.layout.breadcrumbs', [['label' => 'Product name', 'url' => '/', 'current' => false], ['label' => 'Settings', 'url' => '/settings', 'current' => true]]);
    }

    public function test_the_personal_landing_page_lists_the_personal_sections(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->get('/me', $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Sections/Index/Page')
            ->assertJsonPath('props.groups', [['label' => 'Account', 'sections' => [['key' => 'profile', 'label' => 'Profile', 'description' => 'Your name, email address and photo.', 'icon' => 'user', 'url' => '/me/profile'], ['key' => 'password', 'label' => 'Password', 'description' => 'Change your password.', 'icon' => 'key-round', 'url' => '/me/password'], ['key' => 'security', 'label' => 'Security', 'description' => 'Two-factor authentication, signed-in devices and your security log.', 'icon' => 'shield-check', 'url' => '/me/security'], ['key' => 'workspaces', 'label' => 'Workspaces', 'description' => 'The workspaces you belong to.', 'icon' => 'building-2', 'url' => '/me/workspaces']]]])
            ->assertJsonPath('props.layout.breadcrumbs', [['label' => 'Product name', 'url' => '/', 'current' => false], ['label' => 'Account', 'url' => '/me', 'current' => true]]);
        $this->getJson('/me')->assertOk()->assertJsonPath('sections.3.key', 'workspaces');
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
        // The section navigation is the page's `layout.navigation`: the same server data as the landing cards, with the shown one current. The landing pages (the
        // card grids) and the dashboard have none, and the empty list clears what the previous page showed.
        foreach (['/settings/general', '/@alpha/settings/general'] as $path) {
            $nav = $this->get($path, $this->inertia())->assertOk()->json('props.layout.navigation');
            $this->assertCount(1, $nav);
            $this->assertSame('Workspace', $nav[0]['label']);
            $this->assertSame([['General', true], ['People', false], ['Connections', false]], array_map(fn (array $item): array => [$item['label'], $item['current']], $nav[0]['items']));
            $this->assertSame('sliders-horizontal', $nav[0]['items'][0]['icon']);
        }
        $this->get('/settings', $this->inertia())->assertJsonPath('props.layout.navigation', []);
        $this->get('/dashboard', $this->inertia())->assertJsonPath('props.layout.navigation', []);
        // A viewer's navigation lists only what the viewer may open.
        app(Registry::class)->settingsSection('workspace', 'owner-only', 'Owner only', 'Needs write access.', 'settings.write', 'Workspace', 30);
        $labels = array_column($this->get('/settings/general', $this->inertia())->json('props.layout.navigation.0.items'), 'label');
        $this->assertContains('Owner only', $labels);
        $this->postJson('/logout')->assertNoContent();
        $this->signIn(3);
        $this->assertNotContains('Owner only', array_column($this->get('/settings/general', $this->inertia())->json('props.layout.navigation.0.items') ?? [], 'label'));
    }
}
