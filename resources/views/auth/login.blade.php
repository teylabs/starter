<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in</title>
<main><h1>Sign in</h1>
@if ($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('login.store') }}">@csrf
<label>Email <input type="email" name="email" autocomplete="username" value="{{ old('email') }}" required></label>
<label>Password <input type="password" name="password" autocomplete="current-password" required></label>
<button type="submit">Sign in</button></form></main></html>
