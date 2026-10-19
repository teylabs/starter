<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Workspace settings</title>
<main><a href="/workspaces">Workspaces</a><h1>Workspace settings</h1><a href="/workspaces/{{ $settings['workspace_id'] }}/notes">Workspace notes</a><a href="/workspaces/{{ $settings['workspace_id'] }}/connections">Delegated connections</a>
@if (session('status'))<p role="status">{{ session('status') }}</p>@endif
@if ($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="/workspaces/{{ $settings['workspace_id'] }}/settings">@csrf @method('PUT')
<input type="hidden" name="expected_version" value="{{ $settings['version'] }}"><input type="hidden" name="intent_key" value="{{ Illuminate\Support\Str::uuid() }}">
<label>Display name <input name="display_name" value="{{ old('display_name', $settings['display_name']) }}" maxlength="120" required></label>
<label>Timezone <input name="timezone" value="{{ old('timezone', $settings['timezone']) }}" required></label><button>Save settings</button></form></main></html>
