<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Access\Capabilities;
use Tey\Kit\Features\Access\Operations\ManageCapabilities;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Settings\Integrations\Mcp\ReadSettings;
use Tey\Kit\Features\Settings\Integrations\Mcp\UpdateSettings;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/**
 * Capabilities are the app's features, switchable per workspace and per person: the person's override, then the workspace's, then the configured default. Off means absent
 * everywhere it would appear (a 404, no navigation, no settings section, no MCP tool), and on restores it. The guard at the end walks the Registry so a new capability
 * or surface cannot forget it.
 */
final class CapabilityTest extends TestCase
{
    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    private function signIn(int $id): void
    {
        Auth::forgetGuards();
        $password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    }

    private function workspace(): Workspace
    {
        return Workspace::query()->findOrFail(1);
    }

    private function inside(callable $run): mixed
    {
        return app(TenantBoundary::class)->run(1, $run);
    }

    public function test_the_person_beats_the_workspace_and_the_workspace_beats_the_default(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $workspace = $this->workspace();
        $viewer = User::findOrFail(3);
        $capabilities = app(Capabilities::class);
        $active = fn () => $this->inside(fn () => $capabilities->active('workspace.dashboard', $workspace, $viewer));

        $this->assertTrue($active(), 'the configured default is on');
        $this->inside(fn () => $capabilities->setForWorkspace('workspace.dashboard', $workspace, false));
        $this->assertFalse($active(), 'the workspace value beats the default');
        $this->inside(fn () => $capabilities->setForPerson('workspace.dashboard', $workspace, 3, true));
        $this->assertTrue($active(), 'the person beats the workspace');
        $this->assertFalse($this->inside(fn () => $capabilities->active('workspace.dashboard', $workspace, User::findOrFail(2))), 'someone else follows the workspace');
        $this->inside(fn () => $capabilities->setForPerson('workspace.dashboard', $workspace, 3, null));
        $this->assertFalse($active(), 'clearing the person puts them back with the workspace');
        $this->inside(fn () => $capabilities->setForWorkspace('workspace.dashboard', $workspace, null));
        $this->assertTrue($active(), 'clearing the workspace puts it back on the default');
        $this->expectExceptionMessage('unknown_capability');
        $capabilities->setForWorkspace('no.such.capability', $workspace, true);
    }

    public function test_off_is_a_404_and_the_page_is_not_in_the_navigation_and_on_restores_it(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(0, Artisan::call('kit:permissions:sync', ['--no-interaction' => true]));
        $this->signIn(1);
        $workspace = $this->workspace();
        $navigation = fn () => array_column($this->get('/@alpha/settings', $this->inertia())->assertOk()->json('props.shell.navigation'), 'label');
        $this->assertContains('Dashboard', $navigation());
        $this->get('/@alpha/dashboard', $this->inertia())->assertOk();

        $this->inside(fn () => app(Capabilities::class)->setForWorkspace('workspace.dashboard', $workspace, false));
        $this->get('/@alpha/dashboard', $this->inertia())->assertNotFound();
        $this->getJson('/@alpha/dashboard')->assertNotFound();
        $this->assertNotContains('Dashboard', $navigation());

        $this->inside(fn () => app(Capabilities::class)->setForWorkspace('workspace.dashboard', $workspace, null));
        $this->get('/@alpha/dashboard', $this->inertia())->assertOk();
        $this->assertContains('Dashboard', $navigation());
    }

    public function test_a_settings_capability_that_is_off_hides_its_sections_and_its_pages(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(0, Artisan::call('kit:permissions:sync', ['--no-interaction' => true]));
        $this->signIn(1);
        $workspace = $this->workspace();
        $keys = fn () => collect($this->get('/@alpha/settings', $this->inertia())->json('props.groups'))->flatMap(fn ($group) => $group['sections'])->pluck('key')->all();
        $this->assertContains('general', $keys());
        $this->inside(fn () => app(Capabilities::class)->setForWorkspace('workspace.settings', $workspace, false));
        $this->get('/@alpha/settings/general', $this->inertia())->assertNotFound();
        $this->assertNotContains('general', $keys());
        $this->inside(fn () => app(Capabilities::class)->setForWorkspace('workspace.settings', $workspace, null));
        $this->assertContains('general', $keys());
    }

    public function test_an_operator_changes_a_capability_with_a_reason_and_nobody_else_can(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $workspace = $this->workspace();
        $manage = app(ManageCapabilities::class);
        $this->expectsAuditOf = null;
        try {
            $manage->set(new Principal(3), 'workspace.dashboard', 1, null, false, 'because');
            $this->fail('a viewer is not an operator');
        } catch (RuntimeException $e) {
            $this->assertSame('operator_required', $e->getMessage());
        }
        DB::table('operators')->insert(['actor_id' => 1]);
        try {
            $manage->set(new Principal(1), 'workspace.dashboard', 1, null, false, '  ');
            $this->fail('a reason is required');
        } catch (RuntimeException $e) {
            $this->assertSame('reason_required', $e->getMessage());
        }
        $manage->set(new Principal(1), 'workspace.dashboard', 1, 3, false, 'trial of the new dashboard');
        $this->assertFalse($this->inside(fn () => app(Capabilities::class)->active('workspace.dashboard', $workspace, User::findOrFail(3))));
        $this->assertTrue($this->inside(fn () => app(Capabilities::class)->active('workspace.dashboard', $workspace, User::findOrFail(2))));
        $audit = DB::table('audit_events')->where('event', 'capability.changed')->latest('id')->first();
        $this->assertSame(['capability' => 'workspace.dashboard', 'user_id' => 3, 'enabled' => false, 'reason' => 'trial of the new dashboard'], json_decode($audit->details, true));
        // The command shows it, and changes it as a named operator with a reason.
        $this->assertSame(0, Artisan::call('kit:capability', ['workspace' => 'alpha']));
        $this->assertStringContainsString('workspace.dashboard', Artisan::output());
        $this->assertSame(1, Artisan::call('kit:capability', ['workspace' => 'alpha', 'capability' => 'workspace.dashboard', 'state' => 'on']));
        $this->assertSame(0, Artisan::call('kit:capability', ['workspace' => 'alpha', 'capability' => 'workspace.dashboard', 'state' => 'clear', '--user' => 3, '--operator' => 1, '--reason' => 'trial over']));
        $this->assertTrue($this->inside(fn () => app(Capabilities::class)->active('workspace.dashboard', $workspace, User::findOrFail(3))));
    }

    public function test_every_surface_of_every_capability_is_absent_when_it_is_off_and_back_when_it_is_on(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(0, Artisan::call('kit:permissions:sync', ['--no-interaction' => true]));
        $registry = app(Registry::class);
        $workspace = $this->workspace();
        $capabilities = app(Capabilities::class);
        $owner = User::findOrFail(1);
        $mcpTools = [ReadSettings::class, UpdateSettings::class];
        $this->assertNotEmpty($registry->workspaceCapabilities());

        foreach ($registry->workspaceCapabilities() as $capability) {
            // Every ability the capability gates: authorized when on, `capability_unavailable` when off.
            $abilities = array_keys(array_filter($registry->abilityMap(), fn (string $gate): bool => $gate === $capability));
            $this->assertNotEmpty($abilities, $capability.' gates at least one ability');
            $surfaces = function () use ($registry, $owner, $abilities, $mcpTools, $capability): array {
                return $this->inside(function () use ($registry, $owner, $abilities, $mcpTools, $capability): array {
                    $found = ['abilities' => [], 'nav' => [], 'sections' => [], 'tools' => []];
                    foreach ($abilities as $ability) {
                        try {
                            app(Access::class)->authorize(new Principal((int) $owner->getKey()), $ability);
                            $found['abilities'][] = $ability;
                        } catch (RuntimeException $e) {
                            $this->assertContains($e->getMessage(), ['capability_unavailable', 'permission_denied'], $ability.' fails closed');
                            if ($e->getMessage() === 'permission_denied') {
                                $found['abilities'][] = $ability.' (permission only)';
                            }
                        }
                    }
                    foreach ($registry->navigationLinks() as $link) {
                        if (in_array($link['ability'], $abilities, true)) {
                            $found['nav'][] = $this->visible($link['ability'], $owner);
                        }
                    }
                    foreach ($registry->settingsSections('workspace') as $section) {
                        if ($section['ability'] !== null && in_array($section['ability'], $abilities, true)) {
                            $found['sections'][] = $this->visible($section['ability'], $owner);
                        }
                    }
                    foreach ($mcpTools as $tool) {
                        if ($registry->capabilityFor($tool::ABILITY) === $capability) {
                            $request = Request::create('/mcp');
                            $request->setUserResolver(fn () => $owner);
                            app()->instance('request', $request);
                            $found['tools'][] = (new $tool)->shouldRegister();
                        }
                    }

                    return $found;
                });
            };

            $on = $surfaces();
            $this->assertNotEmpty($on['abilities'], $capability.' is on: its abilities authorize');
            $this->inside(fn () => $capabilities->setForWorkspace($capability, $workspace, false));
            $off = $surfaces();
            $this->assertSame([], $off['abilities'], $capability.' is off: no ability authorizes, not even a permission-only one');
            $this->assertNotContains(true, $off['nav'], $capability.' is off: no navigation link');
            $this->assertNotContains(true, $off['sections'], $capability.' is off: no settings section');
            $this->assertNotContains(true, $off['tools'], $capability.' is off: no MCP tool');
            $this->inside(fn () => $capabilities->setForWorkspace($capability, $workspace, null));
            $this->assertSame($on, $surfaces(), $capability.' is back on: every surface returns');
        }
    }

    /** Whether the ability is available to the person here, the way the navigation and the settings landing decide it. */
    private function visible(string $ability, User $user): bool
    {
        try {
            app(Access::class)->authorize(new Principal((int) $user->getKey()), $ability);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
