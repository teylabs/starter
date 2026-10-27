<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\UserState;

test('session dashboard', function () {
    require dirname(__DIR__).'/database/fixture.php';
    $inertia = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    $password = Str::random(40);
    $signIn = function (int $id) use ($password): void {
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        // The welcome steps are their own flow; this test is about the dashboard after them.
        UserState::set($id, 'onboarding.done');
        Auth::forgetGuards();
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    };
    DB::table('audit_events')->insert([
        ['actor_id' => 1, 'workspace_id' => 1, 'event' => 'workspace.settings.updated', 'details' => json_encode(['display_name' => 'Private detail']), 'created_at' => now()],
        ['actor_id' => 2, 'workspace_id' => 2, 'event' => 'beta.only', 'details' => '{}', 'created_at' => now()],
    ]);

    // The owner sees the kit's three widgets in order (then the notes feature's card), with this workspace's activity and no event details.
    $signIn(1);
    $owner = $this->get('/@alpha', $inertia)->assertOk()->assertJsonPath('component', 'Workspace/Dashboard/Page');
    expect(array_column($owner->json('props.widgets'), 'key'))->toBe(['kit.you', 'kit.activity', 'kit.people', 'notes.recent']);
    $owner->assertJsonPath('props.widgets.0.person.name', 'Alpha')->assertJsonPath('props.widgets.0.person.role', 'Owner')
        ->assertJsonPath('props.widgets.1.items.0.event', 'Workspace settings updated')->assertJsonCount(1, 'props.widgets.1.items')
        ->assertJsonPath('props.widgets.2.list', 'dashboard_people')->assertJsonMissingPath('props.dashboard_people');
    expect($owner->getContent())->not->toContain('Private detail')->not->toContain('beta.only');

    // The people list is deferred: its own partial request loads the workspace's members, and no one from another workspace.
    $people = $this->get('/@alpha', [...$inertia, 'X-Inertia-Partial-Component' => 'Workspace/Dashboard/Page', 'X-Inertia-Partial-Data' => 'dashboard_people'])->assertOk();
    expect(array_column($people->json('props.dashboard_people.data'), 'title'))->toBe(['Alpha', 'Viewer']);

    // A viewer does not see the activity widget, which needs members.manage; another workspace's dashboard is not theirs.
    $this->postJson('/logout')->assertNoContent();
    $signIn(3);
    $viewer = $this->get('/@alpha', $inertia)->assertOk();
    expect(array_column($viewer->json('props.widgets'), 'key'))->toBe(['kit.you', 'kit.people', 'notes.recent']);
    $viewer->assertJsonPath('props.widgets.0.person.role', 'Viewer')->assertJsonPath('props.widgets.1.actions', []);
    $this->get('/@beta', $inertia)->assertNotFound();

    // With the dashboard capability off, the dashboard is absent and no widget runs.
    config(['kit.capabilities' => ['workspace.dashboard' => false, 'workspace.settings' => true]]);
    $this->get('/@alpha', $inertia)->assertNotFound();
});
