<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;
use Tey\Concierge\Concierge;
use Tey\Concierge\Enums\AccountStatus;
use Tey\Kit\Features\Workspaces\Models\Workspace;

function mcpTransportRpc(int $connection, string $method, array $params = [])
{
    Auth::forgetGuards();

    return test()->postJson('/mcp/connections/'.$connection, ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
}
test('delegated settings', function () {
    require dirname(__DIR__).'/database/fixture.php';
    foreach (glob(base_path('vendor/laravel/passport/database/migrations/*.php')) as $migration) {
        (require $migration)->up();
    }
    // The consent gate's intent ledger, which replays a capability tool's retry (notes_write) on its intent_key.
    (require base_path('database/migrations/kit/mcp/0001_01_02_000601_create_kit_mcp_intents_table.php'))->up();
    $keys = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($keys, $private);
    $public = openssl_pkey_get_details($keys)['key'];
    config(['passport.private_key' => $private, 'passport.public_key' => $public]);
    mcpTransportRpc(1, 'initialize')->assertUnauthorized();
    $password = Str::random(40);
    User::findOrFail(1)->update(['password' => Hash::make($password)]);
    $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $password])->assertOk();
    $this->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Synthetic delegated client', ['https://client.example.test/callback'], false);
    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $query = ['client_id' => $client->id, 'redirect_uri' => 'https://client.example.test/callback', 'response_type' => 'code', 'scope' => 'mcp:use settings.read settings.write', 'state' => Str::random(24), 'code_challenge' => $challenge, 'code_challenge_method' => 'S256', 'prompt' => 'consent'];
    $this->get('/oauth/authorize?'.http_build_query($query))->assertOk()->assertSee('Authorize')->assertSee('Read workspace settings');
    $approval = $this->post('/oauth/authorize', ['auth_token' => session('authToken')])->assertRedirect();
    parse_str(parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $callback);
    self::assertSame($query['state'], $callback['state']);
    $exchange = ['grant_type' => 'authorization_code', 'client_id' => $client->id, 'redirect_uri' => $query['redirect_uri'], 'code' => $callback['code'], 'code_verifier' => $verifier];
    $invalid = $exchange;
    $invalid['code_verifier'] = Str::random(64);
    $this->postJson('/oauth/token', $invalid)->assertBadRequest();
    $tokenResponse = $this->postJson('/oauth/token', $exchange)->assertOk();
    $bearer = $tokenResponse->json('access_token');
    self::assertIsString($bearer);
    $token = Token::query()->where('user_id', 1)->where('client_id', $client->id)->firstOrFail();
    $grant = ['token_id' => $token->id, 'scopes' => ['settings.read', 'settings.write'], 'intent_key' => 'approve-one'];
    $connection = $this->postJson('/@alpha/settings/connections', $grant)->assertCreated()->json('id');
    $this->postJson('/@alpha/settings/connections', $grant)->assertOk()->assertJsonPath('id', $connection);
    $duplicate = $grant;
    $duplicate['intent_key'] = 'duplicate-approval';
    $this->postJson('/@alpha/settings/connections', $duplicate)->assertConflict()->assertJsonPath('error', 'connection_active');
    self::assertSame(1, DB::table('audit_events')->where('event', 'workspace.connection.approved')->count());
    $this->postJson('/@beta/settings/connections', $grant)->assertNotFound();
    $this->withToken('invalid');
    mcpTransportRpc($connection, 'tools/list')->assertUnauthorized();
    $this->withToken($bearer);
    mcpTransportRpc($connection, 'initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => new stdClass, 'clientInfo' => ['name' => 'Synthetic client', 'version' => '1']])->assertOk()->assertJsonPath('result.serverInfo.name', 'Workspace operations');
    mcpTransportRpc($connection, 'tools/list')->assertOk()->assertJsonCount(8, 'result.tools');
    // A connection granted settings only stays narrow: the dashboard read tool needs dashboard.read (or acts-as-me).
    mcpTransportRpc($connection, 'tools/call', ['name' => 'workspace_dashboard', 'arguments' => new stdClass])->assertOk()->assertJsonPath('result.isError', true)->assertJsonPath('result.content.0.text', 'connection_denied');
    // So does global search: workspace_search needs search.read.
    mcpTransportRpc($connection, 'tools/call', ['name' => 'workspace_search', 'arguments' => ['q' => 'alpha']])->assertOk()->assertJsonPath('result.isError', true)->assertJsonPath('result.content.0.text', 'connection_denied');
    mcpTransportRpc($connection, 'tools/call', ['name' => 'workspace_settings_read', 'arguments' => new stdClass])->assertOk()->assertJsonPath('result.structuredContent.workspace_id', 1);
    $data = ['display_name' => 'MCP Alpha', 'timezone' => 'UTC', 'expected_version' => 1, 'intent_key' => 'mcp-update'];
    mcpTransportRpc($connection, 'tools/call', ['name' => 'workspace_settings_update', 'arguments' => $data])->assertOk()->assertJsonPath('result.structuredContent.version', 2);
    mcpTransportRpc($connection, 'tools/call', ['name' => 'workspace_settings_update', 'arguments' => $data])->assertOk()->assertJsonPath('result.structuredContent.version', 2);
    self::assertSame(1, DB::table('audit_events')->where('event', 'workspace.settings.updated')->count());
    $data['intent_key'] = 'stale-mcp';
    mcpTransportRpc($connection, 'tools/call', ['name' => 'workspace_settings_update', 'arguments' => $data])->assertOk()->assertJsonPath('result.isError', true)->assertJsonPath('result.content.0.text', 'stale_settings');

    // Recovery: re-read and retry at the current version.
    $current = mcpTransportRpc($connection, 'tools/call', ['name' => 'workspace_settings_read', 'arguments' => new stdClass])->assertOk()->json('result.structuredContent.version');
    mcpTransportRpc($connection, 'tools/call', ['name' => 'workspace_settings_update', 'arguments' => [...$data, 'expected_version' => $current, 'intent_key' => 'recover-mcp']])->assertOk()->assertJsonPath('result.structuredContent.version', $current + 1);
    mcpTransportRpc($connection, 'tools/call', ['name' => 'workspace_settings_read', 'arguments' => ['actor_id' => 2, 'workspace_id' => 2]])->assertOk()->assertJsonPath('result.isError', true);
    self::assertNull(Workspace::current());
    $token->update(['scopes' => ['mcp:use', 'settings.read']]);
    $data['expected_version'] = 2;
    mcpTransportRpc($connection, 'tools/call', ['name' => 'workspace_settings_update', 'arguments' => $data])->assertOk()->assertJsonPath('result.isError', true)->assertJsonPath('result.content.0.text', 'connection_denied');
    $token->update(['scopes' => ['mcp:use', 'settings.read', 'settings.write']]);
    $token->update(['expires_at' => now()->subMinute()]);
    mcpTransportRpc($connection, 'tools/list')->assertForbidden();
    $token->update(['expires_at' => now()->addHour()]);
    $this->withHeader('Origin', 'https://foreign.example.test');
    mcpTransportRpc($connection, 'tools/list')->assertForbidden();
    $this->withHeader('Origin', config('app.url'));
    mcpTransportRpc(2, 'tools/list')->assertForbidden();
    // old fixture connection is not a Passport grant
    $account = app(Concierge::class)->account(User::findOrFail(1));
    $account->forceFill(['status' => AccountStatus::Deactivated])->save();
    mcpTransportRpc($connection, 'tools/list')->assertForbidden();
    $account->forceFill(['status' => AccountStatus::Active])->save();

    // A capability that is off is absent: the endpoint answers 404, not 403.
    config(['kit.capabilities' => ['workspace.settings' => false]]);
    mcpTransportRpc($connection, 'tools/list')->assertNotFound();
    config(['kit.capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.notes' => true]]);
    $this->withoutToken();
    Auth::forgetGuards();

    // The AI assistants page is hidden until the connection rework: a 404 by default, and the page itself once the app turns it on.
    $this->get('/@alpha/settings/connections')->assertNotFound();
    config(['kit.mcp.ui' => true]);
    $this->get('/@alpha/settings/connections')->assertOk()->assertSee('Synthetic delegated client');
    $this->deleteJson('/@alpha/settings/connections/'.$connection)->assertOk()->assertJsonPath('revoked', true);
    $this->deleteJson('/@alpha/settings/connections/'.$connection)->assertOk();
    self::assertSame(1, DB::table('audit_events')->where('event', 'workspace.connection.revoked')->count());
    $this->withToken($bearer);

    mcpTransportRpc($connection, 'tools/list')->assertForbidden();
    $this->withoutToken();
    Auth::forgetGuards();
    $verifier = Str::random(64);
    $query['scope'] = 'mcp:use settings.read dashboard.read notes.read notes.write';
    $query['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $this->get('/oauth/authorize?'.http_build_query($query))->assertOk();
    $approval = $this->post('/oauth/authorize', ['auth_token' => session('authToken')])->assertRedirect();
    parse_str(parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $callback);
    $exchange['code'] = $callback['code'];
    $exchange['code_verifier'] = $verifier;
    $newBearer = $this->postJson('/oauth/token', $exchange)->assertOk()->json('access_token');
    $newToken = Token::query()->where('user_id', 1)->where('client_id', $client->id)->where('id', '!=', $token->id)->firstOrFail();
    $newGrant = ['token_id' => $newToken->id, 'scopes' => ['settings.read', 'dashboard.read', 'notes.read', 'notes.write'], 'intent_key' => 'approve-reconnected'];
    $newConnection = $this->postJson('/@alpha/settings/connections', $newGrant)->assertCreated()->json('id');
    $this->withToken($bearer);
    mcpTransportRpc($newConnection, 'tools/list')->assertForbidden();
    $this->withToken($newBearer);
    mcpTransportRpc($newConnection, 'tools/call', ['name' => 'workspace_settings_read', 'arguments' => new stdClass])->assertOk()->assertJsonPath('result.structuredContent.display_name', 'MCP Alpha');
    mcpTransportRpc($newConnection, 'tools/call', ['name' => 'workspace_settings_update', 'arguments' => $data])->assertOk()->assertJsonPath('result.isError', true)->assertJsonPath('result.content.0.text', 'connection_denied');
    // The dashboard over MCP: the widgets this connection's grant covers (dashboard.read, and notes.read for the notes card), not the activity widget behind members.manage; extra arguments are refused.
    $dashboard = mcpTransportRpc($newConnection, 'tools/call', ['name' => 'workspace_dashboard', 'arguments' => new stdClass])->assertOk()->assertJsonPath('result.structuredContent.workspace.id', 1);
    self::assertSame(['kit.you', 'kit.people', 'notes.recent'], array_column($dashboard->json('result.structuredContent.widgets'), 'key'));
    mcpTransportRpc($newConnection, 'tools/call', ['name' => 'workspace_dashboard', 'arguments' => ['workspace_id' => 2]])->assertOk()->assertJsonPath('result.isError', true)->assertJsonPath('result.content.0.text', 'unknown_arguments');
    // Notes over MCP: the same operations as the pages. A write replays on its intent key, a stale version is refused, and a delete asks for confirmation first.
    $written = mcpTransportRpc($newConnection, 'tools/call', ['name' => 'notes_write', 'arguments' => ['body' => 'From the assistant', 'intent_key' => 'mcp-note-1']])->assertOk()->assertJsonPath('result.structuredContent.note.version', 1);
    $noteId = $written->json('result.structuredContent.note_id');
    mcpTransportRpc($newConnection, 'tools/call', ['name' => 'notes_write', 'arguments' => ['body' => 'From the assistant', 'intent_key' => 'mcp-note-1']])->assertOk()->assertJsonPath('result.structuredContent.note_id', $noteId);
    self::assertSame(1, DB::table('kit_notes')->where('body', 'From the assistant')->count());
    mcpTransportRpc($newConnection, 'tools/call', ['name' => 'notes_read', 'arguments' => ['id' => $noteId]])->assertOk()->assertJsonPath('result.structuredContent.author_id', 1);
    mcpTransportRpc($newConnection, 'tools/call', ['name' => 'notes_list', 'arguments' => new stdClass])->assertOk()->assertJsonPath('result.structuredContent.notes.0.id', $noteId);
    mcpTransportRpc($newConnection, 'tools/call', ['name' => 'notes_write', 'arguments' => ['id' => $noteId, 'expected_version' => 9, 'body' => 'Stale', 'intent_key' => 'mcp-note-2']])->assertOk()->assertJsonPath('result.isError', true)->assertJsonPath('result.content.0.text', 'stale_note');
    $ask = mcpTransportRpc($newConnection, 'tools/call', ['name' => 'notes_delete', 'arguments' => ['id' => $noteId, 'expected_version' => 1, 'intent_key' => 'mcp-note-3']])->assertOk()->assertJsonPath('result.structuredContent.status', 'confirmation_required');
    self::assertSame(1, DB::table('kit_notes')->whereNull('deleted_at')->where('id', $noteId)->count());
    mcpTransportRpc($newConnection, 'tools/call', ['name' => 'notes_delete', 'arguments' => ['id' => $noteId, 'expected_version' => 1, 'intent_key' => 'mcp-note-3', 'confirm_token' => $ask->json('result.structuredContent.confirm_token')]])->assertOk()->assertJsonPath('result.structuredContent.deleted', true);

    // Persisted scopes cannot widen the cryptographically granted JWT scopes.
    $newToken->update(['scopes' => ['mcp:use', 'settings.read', 'settings.write']]);
    DB::table('connections')->where('id', $newConnection)->update(['scopes' => json_encode(['settings.read', 'settings.write'])]);
    mcpTransportRpc($newConnection, 'tools/call', ['name' => 'workspace_settings_update', 'arguments' => $data])->assertOk()->assertJsonPath('result.isError', true)->assertJsonPath('result.content.0.text', 'connection_denied');
    $parts = explode('.', $newBearer);
    $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
    $claims['exp'] = time() - 60;
    $encode = static fn ($bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    $unsigned = $parts[0].'.'.$encode(json_encode($claims, JSON_THROW_ON_ERROR));
    openssl_sign($unsigned, $signature, $private, OPENSSL_ALGO_SHA256);
    $this->withToken($unsigned.'.'.$encode($signature));
    mcpTransportRpc($newConnection, 'tools/list')->assertUnauthorized();
    $this->withToken($bearer);
    $token->update(['revoked' => true]);
    mcpTransportRpc($connection, 'tools/list')->assertUnauthorized();
});
