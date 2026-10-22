<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

function capabilityInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function capabilitySignIn(int $id): void
{
    Auth::forgetGuards();
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
function capabilityWorkspace(): Workspace
{
    return Workspace::query()->findOrFail(1);
}
function capabilityInside(callable $run): mixed
{
    return app(TenantBoundary::class)->run(1, $run);
}
test('the person beats the workspace and the workspace beats the default', function () {
    require dirname(__DIR__).'/database/fixture.php';
    $workspace = capabilityWorkspace();
    $viewer = User::findOrFail(3);
    $capabilities = app(Capabilities::class);
    $active = fn () => capabilityInside(fn () => $capabilities->active('workspace.dashboard', $workspace, $viewer));

    expect($active())->toBeTrue('the configured default is on');
    capabilityInside(fn () => $capabilities->setForWorkspace('workspace.dashboard', $workspace, false));
    expect($active())->toBeFalse('the workspace value beats the default');
    capabilityInside(fn () => $capabilities->setForPerson('workspace.dashboard', $workspace, 3, true));
    expect($active())->toBeTrue('the person beats the workspace');
    expect(capabilityInside(fn () => $capabilities->active('workspace.dashboard', $workspace, User::findOrFail(2))))->toBeFalse('someone else follows the workspace');
    capabilityInside(fn () => $capabilities->setForPerson('workspace.dashboard', $workspace, 3, null));
    expect($active())->toBeFalse('clearing the person puts them back with the workspace');
    capabilityInside(fn () => $capabilities->setForWorkspace('workspace.dashboard', $workspace, null));
    expect($active())->toBeTrue('clearing the workspace puts it back on the default');
    $this->expectExceptionMessage('unknown_capability');
    $capabilities->setForWorkspace('no.such.capability', $workspace, true);
});
test('off is a 404 and the page is not in the navigation and on restores it', function () {
    require dirname(__DIR__).'/database/fixture.php';
    expect(Artisan::call('kit:permissions:sync', ['--no-interaction' => true]))->toBe(0);
    capabilitySignIn(1);
    $workspace = capabilityWorkspace();
    $navigation = fn () => array_column($this->get('/@alpha/settings', capabilityInertia())->assertOk()->json('props.shell.navigation'), 'label');
    expect($navigation())->toContain('Dashboard');
    $this->get('/@alpha/dashboard', capabilityInertia())->assertOk();

    capabilityInside(fn () => app(Capabilities::class)->setForWorkspace('workspace.dashboard', $workspace, false));
    $this->get('/@alpha/dashboard', capabilityInertia())->assertNotFound();
    $this->getJson('/@alpha/dashboard')->assertNotFound();
    expect($navigation())->not->toContain('Dashboard');

    capabilityInside(fn () => app(Capabilities::class)->setForWorkspace('workspace.dashboard', $workspace, null));
    $this->get('/@alpha/dashboard', capabilityInertia())->assertOk();
    expect($navigation())->toContain('Dashboard');
});
test('a settings capability that is off hides its sections and its pages', function () {
    require dirname(__DIR__).'/database/fixture.php';
    expect(Artisan::call('kit:permissions:sync', ['--no-interaction' => true]))->toBe(0);
    capabilitySignIn(1);
    $workspace = capabilityWorkspace();
    $keys = fn () => collect($this->get('/@alpha/settings', capabilityInertia())->json('props.groups'))->flatMap(fn ($group) => $group['sections'])->pluck('key')->all();
    expect($keys())->toContain('general');
    capabilityInside(fn () => app(Capabilities::class)->setForWorkspace('workspace.settings', $workspace, false));
    $this->get('/@alpha/settings/general', capabilityInertia())->assertNotFound();
    expect($keys())->not->toContain('general');
    capabilityInside(fn () => app(Capabilities::class)->setForWorkspace('workspace.settings', $workspace, null));
    expect($keys())->toContain('general');
});
test('an operator changes a capability with a reason and nobody else can', function () {
    require dirname(__DIR__).'/database/fixture.php';
    $workspace = capabilityWorkspace();
    $manage = app(ManageCapabilities::class);
    $this->expectsAuditOf = null;
    try {
        $manage->set(new Principal(3), 'workspace.dashboard', 1, null, false, 'because');
        $this->fail('a viewer is not an operator');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('operator_required');
    }
    DB::table('operators')->insert(['actor_id' => 1]);
    try {
        $manage->set(new Principal(1), 'workspace.dashboard', 1, null, false, '  ');
        $this->fail('a reason is required');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('reason_required');
    }
    $manage->set(new Principal(1), 'workspace.dashboard', 1, 3, false, 'trial of the new dashboard');
    expect(capabilityInside(fn () => app(Capabilities::class)->active('workspace.dashboard', $workspace, User::findOrFail(3))))->toBeFalse();
    expect(capabilityInside(fn () => app(Capabilities::class)->active('workspace.dashboard', $workspace, User::findOrFail(2))))->toBeTrue();
    $audit = DB::table('audit_events')->where('event', 'capability.changed')->latest('id')->first();
    expect(json_decode($audit->details, true))->toBe(['capability' => 'workspace.dashboard', 'user_id' => 3, 'enabled' => false, 'reason' => 'trial of the new dashboard']);

    // The command shows it, and changes it as a named operator with a reason.
    expect(Artisan::call('kit:capability', ['workspace' => 'alpha']))->toBe(0);
    $this->assertStringContainsString('workspace.dashboard', Artisan::output());
    expect(Artisan::call('kit:capability', ['workspace' => 'alpha', 'capability' => 'workspace.dashboard', 'state' => 'on']))->toBe(1);
    expect(Artisan::call('kit:capability', ['workspace' => 'alpha', 'capability' => 'workspace.dashboard', 'state' => 'clear', '--user' => 3, '--operator' => 1, '--reason' => 'trial over']))->toBe(0);
    expect(capabilityInside(fn () => app(Capabilities::class)->active('workspace.dashboard', $workspace, User::findOrFail(3))))->toBeTrue();
});
test('every surface of every capability is absent when it is off and back when it is on', function () {
    require dirname(__DIR__).'/database/fixture.php';
    expect(Artisan::call('kit:permissions:sync', ['--no-interaction' => true]))->toBe(0);
    $registry = app(Registry::class);
    $workspace = capabilityWorkspace();
    $capabilities = app(Capabilities::class);
    $owner = User::findOrFail(1);
    $mcpTools = [ReadSettings::class, UpdateSettings::class];
    expect($registry->workspaceCapabilities())->not->toBeEmpty();

    foreach ($registry->workspaceCapabilities() as $capability) {
        // Every ability the capability gates: authorized when on, `capability_unavailable` when off.
        $abilities = array_keys(array_filter($registry->abilityMap(), fn (string $gate): bool => $gate === $capability));
        expect($abilities)->not->toBeEmpty($capability.' gates at least one ability');
        $surfaces = function () use ($registry, $owner, $abilities, $mcpTools, $capability): array {
            return capabilityInside(function () use ($registry, $owner, $abilities, $mcpTools, $capability): array {
                $found = ['abilities' => [], 'nav' => [], 'sections' => [], 'tools' => []];
                foreach ($abilities as $ability) {
                    try {
                        app(Access::class)->authorize(new Principal((int) $owner->getKey()), $ability);
                        $found['abilities'][] = $ability;
                    } catch (RuntimeException $e) {
                        expect(['capability_unavailable', 'permission_denied'])->toContain($e->getMessage());
                        if ($e->getMessage() === 'permission_denied') {
                            $found['abilities'][] = $ability.' (permission only)';
                        }
                    }
                }
                foreach ($registry->navigationLinks() as $link) {
                    if (in_array($link['ability'], $abilities, true)) {
                        $found['nav'][] = capabilityVisible($link['ability'], $owner);
                    }
                }
                foreach ($registry->settingsSections('workspace') as $section) {
                    if ($section['ability'] !== null && in_array($section['ability'], $abilities, true)) {
                        $found['sections'][] = capabilityVisible($section['ability'], $owner);
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
        expect($on['abilities'])->not->toBeEmpty($capability.' is on: its abilities authorize');
        capabilityInside(fn () => $capabilities->setForWorkspace($capability, $workspace, false));
        $off = $surfaces();
        expect($off['abilities'])->toBe([], $capability.' is off: no ability authorizes, not even a permission-only one');
        expect($off['nav'])->not->toContain(true);
        expect($off['sections'])->not->toContain(true);
        expect($off['tools'])->not->toContain(true);
        capabilityInside(fn () => $capabilities->setForWorkspace($capability, $workspace, null));
        expect($surfaces())->toBe($on, $capability.' is back on: every surface returns');
    }
});
/** Whether the ability is available to the person here, the way the navigation and the settings landing decide it. */
function capabilityVisible(string $ability, User $user): bool
{
    try {
        app(Access::class)->authorize(new Principal((int) $user->getKey()), $ability);

        return true;
    } catch (RuntimeException) {
        return false;
    }
}
