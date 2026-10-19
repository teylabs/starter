<?php

use App\Models\User;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require_once __DIR__.'/failures.php';

final class NotesTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    public function test_workspace_notes(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $password = Str::random(40);
        User::findOrFail(1)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->getJson('/workspaces/1/notes')->assertOk()->assertJsonCount(1, 'notes')->assertJsonPath('notes.0.body', 'Alpha private');
        $this->getJson('/workspaces/2/notes?actor_id=2')->assertNotFound();
        $this->getJson('/workspaces/1/notes/2')->assertNotFound();
        $create = ['body' => 'HTTP note', 'intent_key' => 'create-note'];
        $id = $this->postJson('/workspaces/1/notes', $create)->assertCreated()->assertJsonPath('record.version', 1)->json('note_id');
        $this->postJson('/workspaces/1/notes', $create)->assertCreated()->assertJsonPath('note_id', $id);
        self::assertSame(3, DB::table('notes')->count());
        $update = ['body' => 'Changed note', 'expected_version' => 1, 'intent_key' => 'update-note'];
        $this->putJson('/workspaces/1/notes/'.$id, $update)->assertOk()->assertJsonPath('record.version', 2);
        $this->putJson('/workspaces/1/notes/'.$id, $update)->assertOk()->assertJsonPath('record.version', 2);
        $stale = $update;
        $stale['intent_key'] = 'stale-note';
        $this->putJson('/workspaces/1/notes/'.$id, $stale)->assertConflict()->assertJsonPath('error', 'stale_note');
        $foreign = $update;
        $foreign['intent_key'] = 'foreign-note';
        $this->putJson('/workspaces/1/notes/2', $foreign)->assertNotFound();
        $changed = $update;
        $changed['body'] = 'Changed intent';
        $this->putJson('/workspaces/1/notes/'.$id, $changed)->assertConflict()->assertJsonPath('error', 'note_intent_conflict');
        $this->postJson('/workspaces/1/notes', ['body' => '', 'intent_key' => 'invalid'])->assertUnprocessable();
        $this->get('/workspaces/1/notes')->assertOk()->assertSee('Changed note');
        $delete = ['expected_version' => 2, 'intent_key' => 'delete-note'];
        $this->deleteJson('/workspaces/1/notes/'.$id, $delete)->assertOk()->assertJsonPath('deleted', true);
        $this->deleteJson('/workspaces/1/notes/'.$id, $delete)->assertOk()->assertJsonPath('deleted', true);
        $this->getJson('/workspaces/1/notes/'.$id)->assertNotFound();
        DB::table('notes')->insert(['id' => $id, 'workspace_id' => 2, 'body' => 'Foreign replacement', 'version' => 1]);
        $this->deleteJson('/workspaces/1/notes/'.$id, $delete)->assertNotFound();
        self::assertSame('Foreign replacement', DB::table('notes')->where('id', $id)->value('body'));
        self::assertSame(3, DB::table('audit_events')->where('event', 'like', 'workspace.note.%')->count());
        self::assertSame('Beta private', DB::table('notes')->where('workspace_id', 2)->value('body'));
        failInserts('fail_note_audit', 'audit_events', 'synthetic_note_audit_failure');
        try {
            $this->putJson('/workspaces/1/notes/1', ['body' => 'Must roll back', 'expected_version' => 1, 'intent_key' => 'failed-note-audit'])->assertServerError();
        } finally {
            allowInserts('fail_note_audit', 'audit_events');
        }
        self::assertSame('Alpha private', DB::table('notes')->where('id', 1)->value('body'));
        self::assertSame(1, DB::table('notes')->where('id', 1)->value('version'));
        self::assertFalse(DB::table('note_intents')->where('intent_key', 'failed-note-audit')->exists());
        $this->postJson('/logout')->assertNoContent();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
        User::findOrFail(3)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor3@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->getJson('/workspaces/1/notes')->assertOk()->assertJsonPath('can_write', false);
        $this->postJson('/workspaces/1/notes', ['body' => 'Forbidden viewer note', 'intent_key' => 'viewer-write'])->assertForbidden();
        config(['kit.capabilities' => ['workspace.settings' => true, 'workspace.notes' => false]]);
        $this->getJson('/workspaces/1/notes')->assertForbidden();
        self::assertNull(Workspace::current());
    }
}
