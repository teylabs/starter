<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

// Password reset by emailed link is on in the template (config/fortify.php): the sign-in page's "Forgot your password?" leads to a page, the link is mailed
// without telling whether the address has an account, and the emailed link sets a new password under the kit's policy.
function passwordResetInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
test('forgot your password on the sign-in page leads to the reset pages', function () {
    $this->get('/forgot-password', passwordResetInertia())->assertOk()->assertJsonPath('component', 'Auth/ForgotPassword/Page');
    $this->get('/reset-password/a-token?email=actor1@example.test', passwordResetInertia())->assertOk()->assertJsonPath('component', 'Auth/ResetPassword/Page')
        ->assertJsonPath('props.token', 'a-token');
    // The sign-in page is sent the link (the kit draws it only while reset is on), and it is the route this app answers.
    $this->get('/login', passwordResetInertia())->assertOk()->assertJsonPath('component', 'Auth/Login/Page')->assertJsonPath('props.forgot', '/forgot-password');
});
test('a reset link is mailed and sets a new password', function () {
    require dirname(__DIR__).'/database/fixture.php';
    // The policy's breach check asks a range API; the test answers it with no matches and reaches nothing.
    Http::preventStrayRequests();
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
    Notification::fake();
    $user = User::findOrFail(1);
    $old = (string) $user->password;

    // The answer is the same for an address with no account, and nothing is mailed to it.
    $known = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk()->json('message');
    $unknown = $this->postJson('/forgot-password', ['email' => 'nobody@example.test'])->assertOk()->json('message');
    expect($unknown)->toBe($known);
    Notification::assertSentTimes(ResetPassword::class, 1);
    $token = '';
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $new = ['email' => $user->email, 'password' => 'a-fresh-password-41', 'password_confirmation' => 'a-fresh-password-41'];
    $this->postJson('/reset-password', [...$new, 'token' => 'not-the-token'])->assertUnprocessable();
    expect((string) $user->fresh()->password)->toBe($old);
    $this->postJson('/reset-password', [...$new, 'token' => $token, 'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson('/reset-password', [...$new, 'token' => $token])->assertOk();
    expect(Hash::check('a-fresh-password-41', (string) $user->fresh()->password))->toBeTrue();
    // The link works once.
    $this->postJson('/reset-password', [...$new, 'token' => $token, 'password' => 'another-password-52', 'password_confirmation' => 'another-password-52'])->assertUnprocessable();
    $this->postJson('/login', ['email' => $user->email, 'password' => 'a-fresh-password-41'])->assertOk();
});
