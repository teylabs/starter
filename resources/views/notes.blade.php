<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Workspace notes</title><main>
<a href="/workspaces/{{ $workspace }}/settings">Workspace settings</a><h1>Workspace notes</h1>
@if ($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
@if ($canWrite)<h2>Create a note</h2><form method="post" action="/workspaces/{{ $workspace }}/notes">@csrf<input type="hidden" name="intent_key" value="{{ Illuminate\Support\Str::uuid() }}"><label>Note <textarea name="body" maxlength="2000" required></textarea></label><button>Create note</button></form>@endif
<ul>@forelse ($notes as $note)<li><p>{{ $note['body'] }}</p>@if ($canWrite)
<form method="post" action="/workspaces/{{ $workspace }}/notes/{{ $note['id'] }}">@csrf @method('PUT')<input type="hidden" name="expected_version" value="{{ $note['version'] }}"><input type="hidden" name="intent_key" value="{{ Illuminate\Support\Str::uuid() }}"><label>Edit note {{ $note['id'] }} <textarea name="body" maxlength="2000" required>{{ $note['body'] }}</textarea></label><button>Save note {{ $note['id'] }}</button></form>
<form method="post" action="/workspaces/{{ $workspace }}/notes/{{ $note['id'] }}">@csrf @method('DELETE')<input type="hidden" name="expected_version" value="{{ $note['version'] }}"><input type="hidden" name="intent_key" value="{{ Illuminate\Support\Str::uuid() }}"><button>Delete note {{ $note['id'] }}</button></form>@endif</li>@empty<li>No notes yet.</li>@endforelse</ul>
</main></html>
