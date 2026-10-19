<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Authorize client</title><main>
<h1>Authorize {{ $client->name }}</h1><p>This client requests:</p><ul>@foreach ($scopes as $scope)<li>{{ $scope->description }}</li>@endforeach</ul>
<p>Workspace approval is a separate step. This authorization alone grants no workspace access.</p>
<form method="post" action="{{ route('passport.authorizations.approve') }}">@csrf<input type="hidden" name="auth_token" value="{{ $authToken }}"><button>Authorize</button></form>
<form method="post" action="{{ route('passport.authorizations.deny') }}">@csrf @method('DELETE')<input type="hidden" name="auth_token" value="{{ $authToken }}"><button>Deny</button></form>
</main></html>
