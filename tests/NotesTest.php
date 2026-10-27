<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\UserState;

test('session notes', function () {
    require dirname(__DIR__).'/database/fixture.php';
    config(['filesystems.disks.local.root' => storage_path('framework/testing/notes-'.Str::random(8))]);
    $inertia = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    $password = Str::random(40);
    $signIn = function (int $id) use ($password): void {
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        UserState::set($id, 'onboarding.done');
        Auth::forgetGuards();
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    };
    $grant = function (string $permission, bool $on): void {
        $role = Role::query()->where('name', 'viewer')->firstOrFail();
        $on ? $role->givePermissionTo($permission) : $role->revokePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    };

    // The owner writes, with an attachment, and the save flashes a toast.
    $signIn(1);
    $this->post('/@alpha/notes', ['body' => 'Owner note', 'colour' => 'pink', 'intent_key' => 'owner-1', 'files' => [UploadedFile::fake()->image('plan.png', 40, 30)]], $inertia)->assertRedirect();
    expect(json_encode(session()->all()))->toContain('Note added');
    $owner = $this->getJson('/@alpha/notes')->assertOk()->json('notes.0');
    expect($owner['body'])->toBe('Owner note')->and($owner['colour'])->toBe('pink')->and($owner['attachments'])->toHaveCount(1);
    $this->get(parse_url($owner['attachments'][0]['url'], PHP_URL_PATH))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    // Retried with the same intent key, the write happens once.
    $this->postJson('/@alpha/notes', ['body' => 'Owner note', 'colour' => 'pink', 'intent_key' => 'owner-2'])->assertCreated();
    $this->postJson('/@alpha/notes', ['body' => 'Owner note', 'colour' => 'pink', 'intent_key' => 'owner-2'])->assertCreated();
    expect(DB::table('kit_notes')->count())->toBe(2);
    $this->postJson('/@alpha/notes', ['body' => 'Other', 'intent_key' => 'owner-2'])->assertConflict()->assertJsonPath('error', 'note_intent_conflict');
    $this->putJson('/@alpha/notes/'.$owner['id'], ['body' => 'Edited', 'expected_version' => 1, 'intent_key' => 'owner-3'])->assertOk()->assertJsonPath('note.version', 2);
    $this->putJson('/@alpha/notes/'.$owner['id'], ['body' => 'Again', 'expected_version' => 1, 'intent_key' => 'owner-4'])->assertConflict()->assertJsonPath('error', 'stale_note');
    $this->get('/@alpha/notes', $inertia)->assertOk()->assertJsonPath('component', 'Workspace/Notes/Index/Page')->assertJsonPath('props.composer.submit.url', '/notes');
    // Another workspace's notes are not this owner's.
    $this->getJson('/@beta/notes')->assertNotFound();
    $this->postJson('/logout')->assertNoContent();

    // A viewer reads, and cannot write.
    $signIn(3);
    $this->getJson('/@alpha/notes')->assertOk()->assertJsonCount(2, 'notes');
    $this->get('/@alpha/notes', $inertia)->assertOk()->assertJsonPath('props.composer', null);
    $this->postJson('/@alpha/notes', ['body' => 'Viewer', 'intent_key' => 'viewer-1'])->assertForbidden();

    // Given notes.write, the viewer writes and changes their own note, but not the owner's: moderation is its own permission.
    $grant('workspace.notes.manage', true);
    $mine = $this->postJson('/@alpha/notes', ['body' => 'Viewer note', 'intent_key' => 'viewer-2'])->assertCreated()->json('note');
    $this->putJson('/@alpha/notes/'.$mine['id'], ['body' => 'Viewer edit', 'expected_version' => 1, 'intent_key' => 'viewer-3'])->assertOk();
    $this->putJson('/@alpha/notes/'.$owner['id'], ['body' => 'Hijack', 'expected_version' => 2, 'intent_key' => 'viewer-4'])->assertForbidden()->assertJsonPath('error', 'note_not_yours');
    $this->deleteJson('/@alpha/notes/'.$owner['id'], ['expected_version' => 2, 'intent_key' => 'viewer-5'])->assertForbidden();
    // With notes.moderate as well, they may.
    $grant('workspace.notes.moderate', true);
    $this->putJson('/@alpha/notes/'.$owner['id'], ['body' => 'Moderated', 'expected_version' => 2, 'intent_key' => 'viewer-6'])->assertOk()->assertJsonPath('note.version', 3);
    $grant('workspace.notes.moderate', false);
    $grant('workspace.notes.manage', false);
    $this->postJson('/logout')->assertNoContent();

    // The owner moderates by being the owner, and deletes the viewer's note.
    $signIn(1);
    $this->deleteJson('/@alpha/notes/'.$mine['id'], ['expected_version' => 2, 'intent_key' => 'owner-5'])->assertOk()->assertJsonPath('deleted', true);
    expect(DB::table('audit_events')->where('event', 'workspace.note.deleted')->count())->toBe(1);

    // The legacy notes tables an earlier release made are not touched by the feature.
    expect(Schema::hasTable('notes'))->toBeFalse();
});
