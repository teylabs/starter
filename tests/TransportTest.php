<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

test('session settings', function () {
    function transportCheck(bool $result, string $name): void
    {
        if (! $result) {
            throw new RuntimeException($name);
        }
        echo 'PASS '.$name."\n";
    }

    $h = $this;
    require dirname(__DIR__).'/database/fixture.php';
    $password = Str::random(40);
    User::findOrFail(1)->update(['password' => Hash::make($password)]);
    $h->getJson('/me/workspaces')->assertUnauthorized();
    transportCheck(true, 'unauthenticated HTTP cannot choose an actor');
    $h->get('/login')->assertOk()->assertSee('Sign in');
    $h->withCookie(config('session.cookie'), app('session')->getId());
    $h->postJson('/login', ['email' => 'actor1@example.test', 'password' => 'incorrect'])->assertUnprocessable();
    transportCheck(true, 'Fortify refuses invalid credentials');
    Auth::forgetGuards();
    $h->postJson('/login', ['email' => 'actor1@example.test', 'password' => $password])->assertOk();
    $h->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
    $h->getJson('/me/workspaces')->assertOk()->assertJsonPath('workspaces.0.id', 1)->assertJsonCount(1, 'workspaces');
    transportCheck(true, 'Fortify password login establishes a session restricted to own membership');
    $h->getJson('/@alpha/settings/general')->assertOk()->assertJsonPath('workspace_id', 1);
    $h->getJson('/@beta/settings/general?actor_id=2')->assertNotFound();
    transportCheck(true, 'session identity cannot be replaced by a foreign actor argument');
    $data = ['display_name' => 'HTTP Alpha', 'timezone' => 'America/Toronto', 'expected_version' => 1, 'intent_key' => 'http-update'];
    $h->putJson('/@alpha/settings/general', $data)->assertOk()->assertJsonPath('display_name', 'HTTP Alpha')->assertJsonPath('version', 2);
    $h->putJson('/@alpha/settings/general', $data)->assertOk()->assertJsonPath('version', 2);
    transportCheck(DB::table('audit_events')->where('event', 'workspace.settings.updated')->count() === 1, 'HTTP retries share one operation result and audit');
    $data['intent_key'] = 'stale-http';
    $h->putJson('/@alpha/settings/general', $data)->assertConflict()->assertJsonPath('error', 'stale_settings');
    transportCheck(true, 'HTTP stale prepared settings refuse');
    $current = $h->getJson('/@alpha/settings/general')->assertOk()->json('version');
    $h->putJson('/@alpha/settings/general', ['display_name' => 'HTTP Alpha', 'timezone' => 'UTC', 'expected_version' => $current, 'intent_key' => 'recover-http'])->assertOk()->assertJsonPath('version', $current + 1)->assertJsonPath('timezone', 'UTC');
    transportCheck(true, 'HTTP recovers from a stale refusal by re-reading the current version');
    $h->get('/@alpha/settings/general')->assertOk()->assertSee('HTTP Alpha');
    transportCheck(true, 'settings page renders persisted shared-operation result');
    $h->get('/register')->assertNotFound();
    $h->postJson('/register', [])->assertNotFound();
    $h->postJson('/local-login', [])->assertNotFound();
    transportCheck(true, 'registration and local one-click login remain closed');
    app()->instance('env', 'production');
    try {
        $h->putJson('/@alpha/settings/general', $data)->assertStatus(419);
        transportCheck(true, 'real-environment web middleware refuses a missing CSRF token');
    } finally {
        app()->instance('env', 'testing');
    }
    $h->postJson('/logout')->assertNoContent();
    $h->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
    $h->getJson('/me/workspaces')->assertUnauthorized();
    transportCheck(true, 'Fortify logout invalidates the authenticated session');
});
