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
use Tests\TestCase;
use Tey\Kit\Features\SessionUi\Account\OtherSessions;
use Tey\Kit\Features\SessionUi\Account\SecurityLog;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Models\SecurityEvent;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/** The account pages under /me: profile and email re-verification, password, two-factor authentication, signed-in devices and the security log. */
final class AccountTest extends TestCase
{
    private string $password = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.name' => 'Product name']);
    }

    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    private function signIn(int $id, bool $expectOk = true): void
    {
        Auth::forgetGuards();
        $this->password = 'a-long-password-'.Str::random(8);
        User::findOrFail($id)->update(['password' => Hash::make($this->password)]);
        $response = $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $this->password]);
        $expectOk && $response->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    }

    private function signOut(): void
    {
        $this->postJson('/logout')->assertNoContent();
        $this->flushSession();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        Auth::forgetGuards();
    }

    /** The authenticator's code for this time step, or a later one (a code already used cannot be used again). */
    private function codeFor(User $user, int $steps = 0): string
    {
        $engine = app(Google2FA::class);

        return $engine->oathTotp(decrypt($user->fresh()->two_factor_secret), $engine->getTimestamp() + $steps);
    }

    private function sessionId(): string
    {
        return (string) $this->defaultCookies[config('session.cookie')];
    }

    private function events(int $user): array
    {
        return SecurityEvent::query()->where('user_id', $user)->orderBy('id')->pluck('type')->all();
    }

    public function test_the_account_pages_are_the_account_group_on_me(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $groups = $this->get('/me', $this->inertia())->assertOk()->json('props.groups');
        $this->assertSame(['Account'], array_column($groups, 'label'));
        $this->assertSame(['profile', 'password', 'security', 'workspaces'], array_column($groups[0]['sections'], 'key'));
        $this->assertSame(['user', 'key-round', 'shield-check', 'building-2'], array_column($groups[0]['sections'], 'icon'));
        $this->get('/me/profile', $this->inertia())->assertJsonPath('component', 'Workspace/Account/Profile/Page')->assertJsonPath('props.email', 'actor1@example.test')->assertJsonPath('props.profile_url', '/user/profile-information');
        $this->get('/me/password', $this->inertia())->assertJsonPath('component', 'Workspace/Account/Password/Page')->assertJsonPath('props.layout.documentTitle', 'Password · Account · Product name')->assertJsonPath('props.layout.breadcrumbs.2.label', 'Password');
        $this->get('/me/security', $this->inertia())->assertJsonPath('component', 'Workspace/Account/Security/Page')->assertJsonPath('props.two_factor.state', 'off')->assertJsonPath('props.layout.navigation.0.items.2.current', true);
        $this->postJson('/logout')->assertNoContent();
        $this->flushSession();
        $this->get('/me/security')->assertRedirect('/login');
    }

    public function test_a_new_email_stays_unverified_until_confirmed_and_the_user_is_told(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        Notification::fake();
        $this->signIn(1);
        $this->putJson('/user/profile-information', ['name' => 'Alpha Renamed', 'email' => 'actor1@example.test'])->assertOk();
        $this->assertSame('Alpha Renamed', User::findOrFail(1)->name);
        $this->assertNotNull(User::findOrFail(1)->email_verified_at, 'the same address stays verified');
        Notification::assertNothingSent();
        $this->putJson('/user/profile-information', ['name' => 'Alpha Renamed', 'email' => 'new-address@example.test'])->assertOk();
        $user = User::findOrFail(1);
        $this->assertSame('new-address@example.test', $user->email);
        $this->assertNull($user->email_verified_at, 'the new address is not verified yet');
        Notification::assertSentTo($user, VerifyEmail::class);
        $page = $this->get('/me/profile', $this->inertia())->assertOk();
        $page->assertJsonPath('props.email_verified', false)->assertJsonPath('props.notice', 'Your email address changed. We sent a link to the new address; until you confirm it, your access is limited.');
        $this->assertContains('email_changed', $this->events(1));
        // The verification page asks for the link, and the link confirms the address.
        $this->get('/email/verify', $this->inertia())->assertOk()->assertJsonPath('component', 'Auth/VerifyEmail/Page')->assertJsonPath('props.email', 'new-address@example.test');
        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->getKey(), 'hash' => sha1($user->email)]);
        $this->get($link)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
        // Another account's address, a bad one, and an empty name are refused.
        $this->putJson('/user/profile-information', ['name' => 'X', 'email' => 'actor2@example.test'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->putJson('/user/profile-information', ['name' => 'X', 'email' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->putJson('/user/profile-information', ['name' => '', 'email' => 'new-address@example.test'])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_the_password_changes_with_the_current_one_and_signs_other_sessions_out(): void
    {
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
        $this->signIn(1);
        $this->get('/me/password', $this->inertia())->assertOk();
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
        $this->assertTrue(Hash::check($this->password, User::findOrFail(1)->password));
        $this->putJson('/user/password', $new)->assertOk();
        $this->assertTrue(Hash::check('brand-new-pass-77', User::findOrFail(1)->password));
        // Other devices are signed out; this one, and other people's sessions, stay.
        $this->assertSame([], DB::table('sessions')->whereIn('id', ['other-device-1', 'other-device-2'])->pluck('id')->all());
        $this->assertSame(1, DB::table('sessions')->where('user_id', 2)->count());
        $this->assertContains('password_changed', $this->events(1));
        $this->assertContains('sessions_ended', $this->events(1));
    }

    public function test_two_factor_is_turned_on_with_a_code_and_a_password_and_used_to_sign_in(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        // Starting asks for the password again.
        $this->postJson('/me/security/two-factor', [])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/me/security/two-factor', ['password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertNull(User::findOrFail(1)->two_factor_secret);
        $this->postJson('/me/security/two-factor', ['password' => $this->password])->assertCreated()->assertJsonPath('state', 'pending');
        $pending = $this->get('/me/security', $this->inertia())->assertOk()->json('props.two_factor');
        $this->assertSame('pending', $pending['state']);
        $this->assertStringContainsString('<svg', $pending['qr_svg']);
        $this->assertSame(decrypt(User::findOrFail(1)->two_factor_secret), $pending['secret_key']);
        $this->assertSame([], $pending['recovery_codes']);
        // It is not on until a right code is given.
        $this->putJson('/me/security/two-factor', ['code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertNull(User::findOrFail(1)->two_factor_confirmed_at);
        $codes = $this->putJson('/me/security/two-factor', ['code' => $this->codeFor(User::findOrFail(1))])->assertOk()->assertJsonPath('state', 'on')->json('recovery_codes');
        $this->assertCount(8, $codes);
        $this->assertNotNull(User::findOrFail(1)->two_factor_confirmed_at);
        $on = $this->get('/me/security', $this->inertia())->json('props.two_factor');
        $this->assertSame(['on', 8, null, null], [$on['state'], $on['codes_left'], $on['qr_svg'], $on['secret_key']]);
        // New recovery codes need the password and replace the old ones.
        $this->postJson('/me/security/recovery-codes', [])->assertUnprocessable();
        $new = $this->postJson('/me/security/recovery-codes', ['password' => $this->password])->assertCreated()->json('recovery_codes');
        $this->assertCount(8, $new);
        $this->assertSame([], array_intersect($codes, $new));
        $this->signOut();

        // Signing in now stops at the challenge, which is a guest page; a wrong code fails, a right one or a recovery code gets in.
        Auth::forgetGuards();
        $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $this->password])->assertOk()->assertJson(['two_factor' => true]);
        $this->assertGuest();
        $this->get('/two-factor-challenge', $this->inertia())->assertOk()->assertJsonPath('component', 'Auth/TwoFactor/Page')->assertJsonPath('props.title', 'Two-factor verification');
        $this->postJson('/two-factor-challenge', ['code' => '123456'])->assertUnprocessable();
        $this->assertGuest();
        $this->postJson('/two-factor-challenge', ['code' => $this->codeFor(User::findOrFail(1), 1)])->assertNoContent();
        $this->assertAuthenticated();
        $this->signOut();
        $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $this->password])->assertOk()->assertJson(['two_factor' => true]);
        $this->postJson('/two-factor-challenge', ['recovery_code' => $new[0]])->assertNoContent();
        $this->assertAuthenticated();
        $this->assertCount(8, User::findOrFail(1)->recoveryCodes());
        $this->assertNotContains($new[0], User::findOrFail(1)->recoveryCodes(), 'a used recovery code is replaced');
        $this->withCookie(config('session.cookie'), app('session')->getId());

        // Turning it off asks for the password too.
        $this->deleteJson('/me/security/two-factor', ['password' => 'wrong'])->assertUnprocessable();
        $this->assertNotNull(User::findOrFail(1)->two_factor_secret);
        $this->deleteJson('/me/security/two-factor', ['password' => $this->password])->assertOk()->assertJsonPath('state', 'off');
        $this->assertNull(User::findOrFail(1)->two_factor_secret);
        $this->assertSame(['two_factor_enabled', 'two_factor_confirmed', 'recovery_codes_regenerated'], array_values(array_intersect($this->events(1), ['two_factor_enabled', 'two_factor_confirmed', 'recovery_codes_regenerated'])));
        $this->assertContains('two_factor_disabled', $this->events(1));
        $this->assertContains('two_factor_failed', $this->events(1));
    }

    public function test_signed_in_devices_are_listed_and_ended_only_with_the_database_driver(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $page = $this->get('/me/security', $this->inertia())->assertOk()->json('props.sessions');
        $this->assertFalse($page['supported']);
        $this->assertSame([], $page['items']);
        $this->deleteJson('/me/security/sessions', ['password' => $this->password])->assertStatus(409);
        Schema::create('sessions', function ($table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        config(['session.driver' => 'database']);
        $this->get('/me/security', $this->inertia())->assertOk();
        $current = (string) DB::table('sessions')->where('user_id', 1)->value('id');
        $this->assertNotSame('', $current);
        DB::table('sessions')->where('id', $current)->update(['ip_address' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit Version/17.0 Safari/605.1.15']);
        DB::table('sessions')->insert([['id' => 'phone', 'user_id' => 1, 'ip_address' => '198.51.100.4', 'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/604', 'payload' => '', 'last_activity' => time() - 3600]]);
        $page = $this->get('/me/security', $this->inertia())->json('props.sessions');
        $this->assertTrue($page['supported']);
        $this->assertContains('Safari on iOS', array_column($page['items'], 'agent'));
        // The row of the session being read is marked, the others are not.
        $marked = array_column(OtherSessions::list(User::findOrFail(1), 'phone'), 'current', 'ip');
        $this->assertTrue($marked['198.51.100.4']);
        $this->assertFalse($marked['127.0.0.1']);
        $this->deleteJson('/me/security/sessions', [])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->deleteJson('/me/security/sessions', ['password' => $this->password])->assertOk();
        $this->assertSame(0, DB::table('sessions')->where('id', 'phone')->count());
        $this->assertContains('sessions_ended', $this->events(1));
    }

    public function test_the_security_log_records_sign_ins_and_failures_and_is_only_yours(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        // A failed attempt on an account that exists is that account's; one for an address nobody has is nobody's.
        $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => 'wrong-password'])->assertUnprocessable();
        $this->postJson('/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password'])->assertUnprocessable();
        $this->assertSame(['failed_sign_in'], $this->events(1));
        $this->assertSame(1, SecurityEvent::query()->count());
        $this->signIn(1);
        $this->signOut();
        $this->signIn(2);
        $mine = $this->get('/me/security', $this->inertia())->assertOk()->json('props.events');
        $this->assertSame(['Signed in'], array_column($mine, 'label'));
        $this->assertSame(['sign_in'], $this->events(2));
        $this->assertSame(['failed_sign_in', 'sign_in'], $this->events(1));
        $this->signOut();
        $this->signIn(1);
        $log = $this->get('/me/security', $this->inertia())->json('props.events');
        $this->assertSame(['Signed in', 'Signed in', 'Failed sign-in attempt'], array_column($log, 'label'));
        $this->assertSame([1], SecurityEvent::query()->whereIn('id', array_keys($log) === [] ? [0] : SecurityEvent::query()->where('user_id', 1)->pluck('id'))->pluck('user_id')->unique()->values()->all());
        // No route reads another user's log, and nothing about it reaches a workspace page.
        $this->get('/@alpha/dashboard', $this->inertia())->assertJsonMissingPath('props.events');
        $this->assertSame(0, SecurityEvent::query()->whereNull('user_id')->count());
    }

    public function test_signing_in_still_works_when_the_log_table_is_missing(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        Schema::drop('kit_security_events');
        $this->signIn(1);
        $this->assertAuthenticated();
    }

    public function test_a_failed_log_write_is_reported_without_the_ip_or_user_agent(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        Schema::drop('kit_security_events');
        $reported = [];
        app(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported): bool {
            $reported[] = $e;

            return false;
        });
        $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.77', 'HTTP_USER_AGENT' => 'SecretAgent/9.9']);
        SecurityLog::record(1, 'sign_in', $request);
        $this->assertNotEmpty($reported);
        foreach ($reported as $e) {
            for ($link = $e; $link !== null; $link = $link->getPrevious()) {
                $this->assertStringNotContainsString('203.0.113.77', $link->getMessage());
                $this->assertStringNotContainsString('SecretAgent', $link->getMessage());
                $this->assertNotInstanceOf(QueryException::class, $link);
            }
        }
    }

    public function test_the_security_log_goes_with_the_account_and_a_soft_delete_keeps_it(): void
    {
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
        $this->assertSame(0, SecurityEvent::query()->where('user_id', 50)->count());
        $this->assertSame(2, SecurityEvent::query()->where('user_id', 1)->count());
        $this->assertSame(2, SecurityEvent::query()->where('user_id', 51)->count());
        // The foreign key removes the rows in the database itself, whatever the model does.
        DB::table('users')->where('id', 51)->delete();
        $this->assertSame(0, SecurityEvent::query()->where('user_id', 51)->count());
        $this->assertSame(2, SecurityEvent::query()->where('user_id', 1)->count());
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
        $this->assertSame(2, SecurityEvent::query()->where('user_id', 1)->count(), 'a soft delete keeps the rows');
        $soft->force = true;
        SecurityLog::forget($soft);
        $this->assertSame(0, SecurityEvent::query()->where('user_id', 1)->count(), 'a force delete removes them');
    }
}
