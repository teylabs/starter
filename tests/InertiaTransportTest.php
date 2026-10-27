<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Tests\Fixtures\RecordsFixtureProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

test('inertia session pages share real operations and fresh navigation', function () {
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    $inertia = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    $this->get('/login', $inertia)->assertOk()->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Auth/Login/Page')->assertJsonPath('props.shell.product.name', config('app.name'))
        ->assertJsonStructure(['props' => ['shell' => ['product', 'footer']]]);

    // The GuestShell carries its kind, the product and the footer only: no ShellData-only field.
    expect(array_keys(Arr::sortRecursive($this->get('/login', $inertia)->json('props.shell'))))->toBe(['footer', 'kind', 'product']);
    $password = Str::random(40);
    User::findOrFail(1)->update(['password' => Hash::make($password)]);
    $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $password])->assertOk();
    $this->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();

    // One workspace: the list has nothing to choose, so it opens the workspace at its first page.
    $this->get('/me/workspaces', $inertia)->assertRedirect('/');
    $this->get('/', $inertia)->assertOk();
    $this->get('/@alpha/records', $inertia)->assertOk()->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Workspace/Record/Index/Page')->assertJsonPath('props.records.0.title', 'Alpha private')->assertJsonCount(1, 'props.records')
        ->assertJsonPath('props.shell.workspace.id', 1)->assertJsonCount(1, 'props.shell.switcher.choices')->assertJsonCount(4, 'props.shell.navigation')->assertJsonPath('props.shell.navigation.0.label', 'Dashboard')->assertJsonPath('props.shell.navigation.1.label', 'Notes')->assertJsonPath('props.shell.navigation.2.label', 'Records')
        ->assertJsonPath('props.shell.navigation.2.current', true)->assertJsonPath('props.shell.navigation.3.label', 'Settings')
        ->assertJsonPath('props.shell.navigation.3.current', false);

    // A write redirects back to the list, in the short form for a user with one workspace; a validation error returns to the form.
    $this->post('/@alpha/records', ['title' => 'Inertia-created'], $inertia)->assertRedirect('/records');
    $this->get('/@alpha/records', $inertia)->assertOk()->assertJsonCount(2, 'props.records')->assertJsonPath('props.records.1.title', 'Inertia-created');
    $this->from('/@alpha/records')->post('/@alpha/records', ['title' => ''], $inertia)->assertRedirect('/@alpha/records')->assertSessionHasErrors('title');
    $this->get('/@alpha/records', $inertia)->assertOk()->assertJsonPath('props.errors.title', 'The title field is required.');
    $created = $this->get('/@alpha/records', $inertia)->json('props.records.1.id');
    $this->put('/@alpha/records/'.$created, ['title' => 'Inertia updated'], $inertia)->assertStatus(303)->assertRedirect('/records');
    $this->get('/@alpha/records', $inertia)->assertOk()->assertJsonPath('props.records.1.title', 'Inertia updated');
    $this->delete('/@alpha/records/'.$created, [], $inertia)->assertStatus(303)->assertRedirect('/records');
    $this->get('/@alpha/records', $inertia)->assertOk()->assertJsonCount(1, 'props.records');

    // Another workspace's record is not found, never shown.
    $this->get('/@alpha/records/2', $inertia)->assertNotFound();
    $settings = $this->get('/@alpha/settings/general', $inertia)->assertOk()->assertJsonPath('component', 'Workspace/Settings/General/Page')->assertJsonPath('props.save.disabled', false)->json('props');
    $payload = ['display_name' => 'Inertia Alpha', 'timezone' => 'America/Toronto', 'expected_version' => $settings['settings']['version'], 'intent_key' => $settings['intent_key']];
    $this->put('/@alpha/settings/general', $payload, $inertia)->assertStatus(303)->assertRedirect('/settings/general');
    $payload['intent_key'] = 'stale-inertia';
    $this->put('/@alpha/settings/general', $payload, $inertia)->assertConflict()->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Workspace/Error/Page')->assertJsonPath('props.status', 409)
        ->assertJsonPath('props.message', 'These settings changed since you opened the form. Reload and review the current values.');
    $this->get('/@beta/records', $inertia)->assertNotFound()->assertJsonPath('component', 'Workspace/Error/Page')
        ->assertJsonCount(1, 'props.shell.switcher.choices')->assertJsonPath('props.shell.workspace.id', 1)->assertJsonPath('props.shell.workspace.all.url', '/me/workspaces');
    config(['kit.capabilities' => ['workspace.dashboard' => true, 'workspace.settings' => true, 'workspace.records' => true]]);
    Feature::define('workspace.records', fn (): bool => false);
    $this->get('/@alpha/settings/general', $inertia)->assertOk()->assertJsonCount(2, 'props.shell.navigation')->assertJsonPath('props.shell.navigation.1.label', 'Settings')
        ->assertJsonPath('props.shell.navigation.1.current', true)->assertJsonPath('props.shell.switcher.choices.0.url', '/');

    // A capability that is off is absent: a 404, not a 403.
    $this->get('/@alpha/records', $inertia)->assertNotFound()->assertJsonPath('component', 'Workspace/Error/Page');
    Feature::define('workspace.records', fn (): bool => true);
    config(['kit.capabilities' => ['workspace.dashboard' => true, 'workspace.settings' => true]]);
    $this->postJson('/logout')->assertNoContent();
    User::findOrFail(3)->update(['password' => Hash::make($password)]);
    Auth::forgetGuards();
    $this->postJson('/login', ['email' => 'actor3@example.test', 'password' => $password])->assertOk();
    $this->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
    $this->get('/@alpha/settings/general', $inertia)->assertOk()->assertJsonPath('props.save', null);
    $this->get('/@alpha/records', $inertia)->assertOk();
    $this->post('/@alpha/records', ['title' => 'forged owner'], $inertia)->assertForbidden()->assertJsonPath('component', 'Workspace/Error/Page');
    expect(DB::table('fixture_records')->where('workspace_id', 2)->value('title'))->toBe('Beta private');
    expect(Workspace::current())->toBeNull();
});
