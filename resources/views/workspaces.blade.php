<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Workspaces</title>
<main><h1>Your workspaces</h1><ul>@forelse ($workspaces as $workspace)<li><a href="/workspaces/{{ $workspace['id'] }}/settings">{{ $workspace['name'] }}</a></li>@empty<li>No active workspaces.</li>@endforelse</ul>
<h2>Accept a workspace invitation</h2><form method="post" action="/tenant-invitations/accept">@csrf<label>Invitation token <input name="token" type="password" required autocomplete="off"></label><button>Accept invitation</button></form>
<form method="post" action="{{ route('logout') }}">@csrf<button>Sign out</button></form></main></html>
