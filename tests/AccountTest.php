<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tey\Kit\Features\SessionUi\Account\OtherSessions;
use Tey\Kit\Features\SessionUi\Account\SecurityLog;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Models\SecurityEvent;

beforeEach(function () {
    config(['app.name' => 'Product name']);
});
function accountInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function accountSignIn(int $id, bool $expectOk = true): void
{
    Auth::forgetGuards();
    test()->password = 'a-long-password-'.Str::random(8);
    User::findOrFail($id)->update(['password' => Hash::make(test()->password)]);
    $response = test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => test()->password]);
    $expectOk && $response->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
function accountSignOut(): void
{
    test()->postJson('/logout')->assertNoContent();
    test()->flushSession();
    test()->forgetCookies();
    Auth::forgetGuards();
}
/** The authenticator's code for this time step, or a later one (a code already used cannot be used again). */
function accountCodeFor(User $user, int $steps = 0): string
{
    $engine = app(Google2FA::class);

    return $engine->oathTotp(decrypt($user->fresh()->two_factor_secret), $engine->getTimestamp() + $steps);
}
function accountSessionId(): string
{
    return (string) test()->defaultCookie(config('session.cookie'));
}
function accountEvents(int $user): array
{
    return SecurityEvent::query()->where('user_id', $user)->orderBy('id')->pluck('type')->all();
}
test('the account pages are the account group on me', function () {
    require dirname(__DIR__).'/database/fixture.php';
    accountSignIn(1);
    $groups = $this->get('/me', accountInertia())->assertOk()->json('props.groups');
    expect(array_column($groups, 'label'))->toBe(['Account']);
    expect(array_column($groups[0]['sections'], 'key'))->toBe(['profile', 'password', 'security', 'workspaces']);
    expect(array_column($groups[0]['sections'], 'icon'))->toBe(['user', 'key-round', 'shield-check', 'building-2']);
    $this->get('/me/profile', accountInertia())->assertJsonPath('component', 'Workspace/Account/Profile/Page')->assertJsonPath('props.email', 'actor1@example.test')->assertJsonPath('props.profile_url', '/user/profile-information');
    $this->get('/me/password', accountInertia())->assertJsonPath('component', 'Workspace/Account/Password/Page')->assertJsonPath('props.layout.documentTitle', 'Password · Account · Product name')->assertJsonPath('props.layout.breadcrumbs.2.label', 'Password');
    $this->get('/me/security', accountInertia())->assertJsonPath('component', 'Workspace/Account/Security/Page')->assertJsonPath('props.two_factor.state', 'off')->assertJsonPath('props.layout.navigation.0.items.2.current', true);
    $this->postJson('/logout')->assertNoContent();
    $this->flushSession();
    $this->get('/me/security')->assertRedirect('/login');
});
test('a new email stays unverified until confirmed and the user is told', function () {
    require dirname(__DIR__).'/database/fixture.php';
    Notification::fake();
    accountSignIn(1);
    $this->putJson('/user/profile-information', ['name' => 'Alpha Renamed', 'email' => 'actor1@example.test'])->assertOk();
    expect(User::findOrFail(1)->name)->toBe('Alpha Renamed');
    expect(User::findOrFail(1)->email_verified_at)->not->toBeNull('the same address stays verified');
    Notification::assertNothingSent();
    $this->putJson('/user/profile-information', ['name' => 'Alpha Renamed', 'email' => 'new-address@example.test'])->assertOk();
    $user = User::findOrFail(1);
    expect($user->email)->toBe('new-address@example.test');
    expect($user->email_verified_at)->toBeNull('the new address is not verified yet');
    Notification::assertSentTo($user, VerifyEmail::class);
    $page = $this->get('/me/profile', accountInertia())->assertOk();
    $page->assertJsonPath('props.email_verified', false)->assertJsonPath('props.notice', 'Your email address changed. We sent a link to the new address; until you confirm it, your access is limited.');
    expect(accountEvents(1))->toContain('email_changed');

    // The verification page asks for the link, and the link confirms the address.
    $this->get('/email/verify', accountInertia())->assertOk()->assertJsonPath('component', 'Auth/VerifyEmail/Page')->assertJsonPath('props.email', 'new-address@example.test');
    $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->getKey(), 'hash' => sha1($user->email)]);
    $this->get($link)->assertRedirect();
    expect($user->fresh()->email_verified_at)->not->toBeNull();

    // Another account's address, a bad one, and an empty name are refused.
    $this->putJson('/user/profile-information', ['name' => 'X', 'email' => 'actor2@example.test'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->putJson('/user/profile-information', ['name' => 'X', 'email' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->putJson('/user/profile-information', ['name' => '', 'email' => 'new-address@example.test'])->assertUnprocessable()->assertJsonValidationErrors('name');
});
test('the password changes with the current one and signs other sessions out', function () {
    require dirname(__DIR__).'/database/fixture.php';
    Schema::create('sessions', function ($table): void {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });
    config(['session.driver' => 'database']);
    accountSignIn(1);
    $this->get('/me/password', accountInertia())->assertOk();
    $current = (string) DB::table('sessions')->where('user_id', 1)->value('id');
    $this->assertNotSame('', $current, 'the request wrote its own session row');
    foreach (['other-device-1', 'other-device-2'] as $id) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => 1, 'ip_address' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0 Safari/537.36', 'payload' => '', 'last_activity' => time() - 60]);
    }
    DB::table('sessions')->where('id', $current)->update(['ip_address' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X) Safari/605']);
    DB::table('sessions')->insert(['id' => 'someone-else', 'user_id' => 2, 'payload' => '', 'last_activity' => time()]);
    $new = ['current_password' => $this->password, 'password' => 'brand-new-pass-77', 'password_confirmation' => 'brand-new-pass-77'];
    $this->putJson('/user/password', [...$new, 'current_password' => 'not-my-password'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    $this->putJson('/user/password', [...$new, 'password_confirmation' => 'different-77'])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->putJson('/user/password', [...$new, 'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect(Hash::check($this->password, User::findOrFail(1)->password))->toBeTrue();
    $this->putJson('/user/password', $new)->assertOk();
    expect(Hash::check('brand-new-pass-77', User::findOrFail(1)->password))->toBeTrue();

    // Other devices are signed out; this one, and other people's sessions, stay.
    expect(DB::table('sessions')->whereIn('id', ['other-device-1', 'other-device-2'])->pluck('id')->all())->toBe([]);
    expect(DB::table('sessions')->where('user_id', 2)->count())->toBe(1);
    expect(accountEvents(1))->toContain('password_changed');
    expect(accountEvents(1))->toContain('sessions_ended');
});
test('two factor is turned on with a code and a password and used to sign in', function () {
    require dirname(__DIR__).'/database/fixture.php';
    accountSignIn(1);

    // Starting asks for the password again.
    $this->postJson('/me/security/two-factor', [])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson('/me/security/two-factor', ['password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect(User::findOrFail(1)->two_factor_secret)->toBeNull();
    $this->postJson('/me/security/two-factor', ['password' => $this->password])->assertCreated()->assertJsonPath('state', 'pending');
    $pending = $this->get('/me/security', accountInertia())->assertOk()->json('props.two_factor');
    expect($pending['state'])->toBe('pending');
    $this->assertStringContainsString('<svg', $pending['qr_svg']);
    expect($pending['secret_key'])->toBe(decrypt(User::findOrFail(1)->two_factor_secret));
    expect($pending['recovery_codes'])->toBe([]);

    // It is not on until a right code is given.
    $this->putJson('/me/security/two-factor', ['code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');
    expect(User::findOrFail(1)->two_factor_confirmed_at)->toBeNull();
    $codes = $this->putJson('/me/security/two-factor', ['code' => accountCodeFor(User::findOrFail(1))])->assertOk()->assertJsonPath('state', 'on')->json('recovery_codes');
    expect($codes)->toHaveCount(8);
    expect(User::findOrFail(1)->two_factor_confirmed_at)->not->toBeNull();
    $on = $this->get('/me/security', accountInertia())->json('props.two_factor');
    expect([$on['state'], $on['codes_left'], $on['qr_svg'], $on['secret_key']])->toBe(['on', 8, null, null]);

    // New recovery codes need the password and replace the old ones.
    $this->postJson('/me/security/recovery-codes', [])->assertUnprocessable();
    $new = $this->postJson('/me/security/recovery-codes', ['password' => $this->password])->assertCreated()->json('recovery_codes');
    expect($new)->toHaveCount(8);
    expect(array_intersect($codes, $new))->toBe([]);
    accountSignOut();

    // Signing in now stops at the challenge, which is a guest page; a wrong code fails, a right one or a recovery code gets in.
    Auth::forgetGuards();
    $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $this->password])->assertOk()->assertJson(['two_factor' => true]);
    $this->assertGuest();
    $this->get('/two-factor-challenge', accountInertia())->assertOk()->assertJsonPath('component', 'Auth/TwoFactor/Page')->assertJsonPath('props.title', 'Two-factor verification');
    $this->postJson('/two-factor-challenge', ['code' => '123456'])->assertUnprocessable();
    $this->assertGuest();
    $this->postJson('/two-factor-challenge', ['code' => accountCodeFor(User::findOrFail(1), 1)])->assertNoContent();
    $this->assertAuthenticated();
    accountSignOut();
    $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $this->password])->assertOk()->assertJson(['two_factor' => true]);
    $this->postJson('/two-factor-challenge', ['recovery_code' => $new[0]])->assertNoContent();
    $this->assertAuthenticated();
    expect(User::findOrFail(1)->recoveryCodes())->toHaveCount(8);
    expect(User::findOrFail(1)->recoveryCodes())->not->toContain($new[0]);
    $this->withCookie(config('session.cookie'), app('session')->getId());

    // Turning it off asks for the password too.
    $this->deleteJson('/me/security/two-factor', ['password' => 'wrong'])->assertUnprocessable();
    expect(User::findOrFail(1)->two_factor_secret)->not->toBeNull();
    $this->deleteJson('/me/security/two-factor', ['password' => $this->password])->assertOk()->assertJsonPath('state', 'off');
    expect(User::findOrFail(1)->two_factor_secret)->toBeNull();
    expect(array_values(array_intersect(accountEvents(1), ['two_factor_enabled', 'two_factor_confirmed', 'recovery_codes_regenerated'])))->toBe(['two_factor_enabled', 'two_factor_confirmed', 'recovery_codes_regenerated']);
    expect(accountEvents(1))->toContain('two_factor_disabled');
    expect(accountEvents(1))->toContain('two_factor_failed');
});
test('signed in devices are listed and ended only with the database driver', function () {
    require dirname(__DIR__).'/database/fixture.php';

    // Not the database driver to begin with, whatever an earlier test or the environment left in the config; put back afterwards.
    $driver = config('session.driver');
    config(['session.driver' => 'array']);
    try {
        accountDevicesAreListedAndEndedOnlyWithTheDatabaseDriver();
    } finally {
        config(['session.driver' => $driver]);
    }
});
function accountDevicesAreListedAndEndedOnlyWithTheDatabaseDriver(): void
{
    accountSignIn(1);
    $page = test()->get('/me/security', accountInertia())->assertOk()->json('props.sessions');
    expect($page['supported'])->toBeFalse();
    expect($page['items'])->toBe([]);
    test()->deleteJson('/me/security/sessions', ['password' => test()->password])->assertStatus(409);
    Schema::create('sessions', function ($table): void {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });
    config(['session.driver' => 'database']);
    test()->get('/me/security', accountInertia())->assertOk();
    $current = (string) DB::table('sessions')->where('user_id', 1)->value('id');
    test()->assertNotSame('', $current);
    DB::table('sessions')->where('id', $current)->update(['ip_address' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit Version/17.0 Safari/605.1.15']);
    DB::table('sessions')->insert([['id' => 'phone', 'user_id' => 1, 'ip_address' => '198.51.100.4', 'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/604', 'payload' => '', 'last_activity' => time() - 3600]]);
    $page = test()->get('/me/security', accountInertia())->assertOk()->json('props.sessions');
    expect($page['supported'])->toBeTrue();
    expect(array_column($page['items'], 'agent'))->toContain('Safari on iOS');

    // The row of the session being read is marked, the others are not.
    $marked = array_column(OtherSessions::list(User::findOrFail(1), 'phone'), 'current', 'ip');
    expect($marked['198.51.100.4'])->toBeTrue();
    expect($marked['127.0.0.1'])->toBeFalse();
    test()->deleteJson('/me/security/sessions', [])->assertUnprocessable()->assertJsonValidationErrors('password');
    test()->deleteJson('/me/security/sessions', ['password' => test()->password])->assertOk();
    expect(DB::table('sessions')->where('id', 'phone')->count())->toBe(0);
    expect(accountEvents(1))->toContain('sessions_ended');
}
test('the security log records sign ins and failures and is only yours', function () {
    require dirname(__DIR__).'/database/fixture.php';

    // A failed attempt on an account that exists is that account's; one for an address nobody has is nobody's.
    $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => 'wrong-password'])->assertUnprocessable();
    $this->postJson('/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password'])->assertUnprocessable();
    expect(accountEvents(1))->toBe(['failed_sign_in']);
    expect(SecurityEvent::query()->count())->toBe(1);
    accountSignIn(1);
    accountSignOut();
    accountSignIn(2);
    $mine = $this->get('/me/security', accountInertia())->assertOk()->json('props.events');
    expect(array_column($mine, 'label'))->toBe(['Signed in']);
    expect(accountEvents(2))->toBe(['sign_in']);
    expect(accountEvents(1))->toBe(['failed_sign_in', 'sign_in']);
    accountSignOut();
    accountSignIn(1);
    $log = $this->get('/me/security', accountInertia())->json('props.events');
    expect(array_column($log, 'label'))->toBe(['Signed in', 'Signed in', 'Failed sign-in attempt']);
    expect(SecurityEvent::query()->whereIn('id', array_keys($log) === [] ? [0] : SecurityEvent::query()->where('user_id', 1)->pluck('id'))->pluck('user_id')->unique()->values()->all())->toBe([1]);

    // No route reads another user's log, and nothing about it reaches a workspace page.
    $this->get('/@alpha', accountInertia())->assertJsonMissingPath('props.events');
    expect(SecurityEvent::query()->whereNull('user_id')->count())->toBe(0);
});
test('signing in still works when the log table is missing', function () {
    require dirname(__DIR__).'/database/fixture.php';
    Schema::drop('kit_security_events');
    accountSignIn(1);
    $this->assertAuthenticated();
});
test('a failed log write is reported without the ip or user agent', function () {
    require dirname(__DIR__).'/database/fixture.php';
    Schema::drop('kit_security_events');
    $reported = [];
    app(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported): bool {
        $reported[] = $e;

        return false;
    });
    $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.77', 'HTTP_USER_AGENT' => 'SecretAgent/9.9']);
    SecurityLog::record(1, 'sign_in', $request);
    expect($reported)->not->toBeEmpty();
    foreach ($reported as $e) {
        for ($link = $e; $link !== null; $link = $link->getPrevious()) {
            $this->assertStringNotContainsString('203.0.113.77', $link->getMessage());
            $this->assertStringNotContainsString('SecretAgent', $link->getMessage());
            $this->assertNotInstanceOf(QueryException::class, $link);
        }
    }
});
test('the security log goes with the account and a soft delete keeps it', function () {
    require dirname(__DIR__).'/database/fixture.php';
    foreach ([50, 51] as $id) {
        User::create(['id' => $id, 'name' => 'Temp '.$id, 'email' => 'temp'.$id.'@example.test']);
    }
    foreach ([1, 50, 51] as $id) {
        SecurityLog::record($id, 'sign_in');
        SecurityLog::record($id, 'password_changed');
    }

    // Deleting a user removes their rows and leaves everyone else's.
    User::findOrFail(50)->delete();
    expect(SecurityEvent::query()->where('user_id', 50)->count())->toBe(0);
    expect(SecurityEvent::query()->where('user_id', 1)->count())->toBe(2);
    expect(SecurityEvent::query()->where('user_id', 51)->count())->toBe(2);

    // The foreign key removes the rows in the database itself, whatever the model does.
    DB::table('users')->where('id', 51)->delete();
    expect(SecurityEvent::query()->where('user_id', 51)->count())->toBe(0);
    expect(SecurityEvent::query()->where('user_id', 1)->count())->toBe(2);

    // A model that soft-deletes keeps the log until the force delete.
    $soft = new class
    {
        public bool $force = false;

        public function isForceDeleting(): bool
        {
            return $this->force;
        }

        public function getKey(): int
        {
            return 1;
        }
    };
    SecurityLog::forget($soft);
    expect(SecurityEvent::query()->where('user_id', 1)->count())->toBe(2, 'a soft delete keeps the rows');
    $soft->force = true;
    SecurityLog::forget($soft);
    expect(SecurityEvent::query()->where('user_id', 1)->count())->toBe(0, 'a force delete removes them');
});
