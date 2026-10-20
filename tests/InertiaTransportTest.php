<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';

final class InertiaTransportTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_inertia_session_pages_share_real_operations_and_fresh_navigation(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $inertia = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
        $this->get('/login', $inertia)->assertOk()->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Auth/SignIn/Page')->assertJsonPath('props.layout.user', null)
            ->assertJsonPath('props.layout.switcher.choices', [])->assertJsonPath('props.layout.navigation', []);
        $password = Str::random(40);
        User::findOrFail(1)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
        // One workspace: the list has nothing to choose, so it opens the workspace at its first page.
        $this->get('/me/workspaces', $inertia)->assertRedirect('/notes');
        $this->get('/', $inertia)->assertRedirect('/me/workspaces');
        $page = $this->get('/@alpha/notes', $inertia)->assertOk()->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Workspace/Note/Index/Page')->assertJsonPath('props.can_write', true)
            ->assertJsonPath('props.notes.0.body', 'Alpha private')->assertJsonCount(1, 'props.notes')
            ->assertJsonPath('props.layout.switcher.current.id', 1)->assertJsonCount(2, 'props.layout.navigation')->assertJsonPath('props.layout.navigation.0.label', 'Notes')
            ->assertJsonPath('props.layout.navigation.0.current', true)->assertJsonPath('props.layout.navigation.1.label', 'Settings')
            ->assertJsonPath('props.layout.navigation.1.current', false)->json();
        $intent = $page['props']['create_intent'];
        $this->post('/@alpha/notes', ['body' => 'Inertia-created', 'intent_key' => $intent], $inertia)->assertRedirect('/notes');
        $this->post('/@alpha/notes', ['body' => 'Inertia-created', 'intent_key' => $intent], $inertia)->assertRedirect('/notes');
        $this->assertSame(1, DB::table('audit_events')->where('event', 'workspace.note.create')->count());
        $this->get('/@alpha/notes', $inertia)->assertOk()->assertJsonCount(2, 'props.notes')->assertJsonPath('props.notes.1.body', 'Inertia-created');
        $this->from('/@alpha/notes')->post('/@alpha/notes', ['body' => '', 'intent_key' => 'invalid'], $inertia)->assertRedirect('/@alpha/notes')->assertSessionHasErrors('body');
        $this->get('/@alpha/notes', $inertia)->assertOk()->assertJsonPath('props.errors.body', 'The body field is required.');
        $notesPage = $this->get('/@alpha/notes', $inertia)->assertOk()->json('props');
        $created = $notesPage['notes'][1];
        $action = $notesPage['actions'][$created['id']];
        $edit = ['body' => 'Inertia updated', 'expected_version' => 1, 'intent_key' => $action['update_intent']];
        $this->put($action['url'], $edit, $inertia)->assertStatus(303)->assertRedirect('/notes');
        $edit['intent_key'] = 'stale-note-inertia';
        $this->put($action['url'], $edit, $inertia)->assertConflict()->assertJsonPath('component', 'Workspace/Error/Page')->assertJsonPath('props.status', 409);
        $this->get('/@alpha/notes', $inertia)->assertOk()->assertJsonPath('props.notes.1.body', 'Inertia updated')->assertJsonPath('props.notes.1.version', 2);
        $remove = ['expected_version' => 2, 'intent_key' => $action['delete_intent']];
        $this->delete($action['url'], $remove, $inertia)->assertStatus(303)->assertRedirect('/notes');
        $this->delete($action['url'], $remove, $inertia)->assertStatus(303)->assertRedirect('/notes');
        $this->get('/@alpha/notes', $inertia)->assertOk()->assertJsonCount(1, 'props.notes');
        $settings = $this->get('/@alpha/settings/general', $inertia)->assertOk()->assertJsonPath('component', 'Workspace/Settings/Edit/Page')->assertJsonPath('props.can_write', true)->json('props');
        $payload = ['display_name' => 'Inertia Alpha', 'timezone' => 'America/Toronto', 'expected_version' => $settings['settings']['version'], 'intent_key' => $settings['intent_key']];
        $this->put('/@alpha/settings/general', $payload, $inertia)->assertStatus(303)->assertRedirect('/settings/general');
        $payload['intent_key'] = 'stale-inertia';
        $this->put('/@alpha/settings/general', $payload, $inertia)->assertConflict()->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Workspace/Error/Page')->assertJsonPath('props.status', 409)
            ->assertJsonPath('props.message', 'These settings changed since you opened the form. Reload and review the current values.');
        $this->get('/@beta/notes', $inertia)->assertNotFound()->assertJsonPath('component', 'Workspace/Error/Page')
            ->assertJsonPath('props.layout.switcher.current', null)->assertJsonCount(1, 'props.layout.switcher.choices');
        config(['kit.capabilities' => ['workspace.settings' => true, 'workspace.notes' => false]]);
        $this->get('/@alpha/settings/general', $inertia)->assertOk()->assertJsonCount(1, 'props.layout.navigation')->assertJsonPath('props.layout.navigation.0.label', 'Settings')
            ->assertJsonPath('props.layout.navigation.0.current', true)->assertJsonPath('props.layout.switcher.choices.0.url', '/settings');
        $this->get('/@alpha/notes', $inertia)->assertForbidden()->assertJsonPath('component', 'Workspace/Error/Page');
        config(['kit.capabilities' => ['workspace.settings' => true, 'workspace.notes' => true]]);
        $this->postJson('/logout')->assertNoContent();
        User::findOrFail(3)->update(['password' => Hash::make($password)]);
        Auth::forgetGuards();
        $this->postJson('/login', ['email' => 'actor3@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
        $this->get('/@alpha/settings/general', $inertia)->assertOk()->assertJsonPath('props.can_write', false);
        $this->get('/@alpha/notes', $inertia)->assertOk()->assertJsonPath('props.can_write', false);
        $this->post('/@alpha/notes', ['body' => 'forged owner', 'intent_key' => 'forged'], $inertia)->assertForbidden()->assertJsonPath('component', 'Workspace/Error/Page');
        $this->assertSame('Beta private', DB::table('notes')->where('workspace_id', 2)->value('body'));
        $this->assertNull(Workspace::current());
    }
}
