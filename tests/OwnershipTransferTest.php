<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tey\Concierge\Concierge;
use Tey\Concierge\Models\Membership;
use Tey\Concierge\Models\Ownership;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require dirname(__DIR__).'/vendor/autoload.php';

/** Transferring ownership: the owner only, to an existing active member, with the password confirmed again; the old owner becomes an admin. */
final class OwnershipTransferTest extends TestCase
{
    private string $password = '';

    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    private function signIn(int $id): void
    {
        Auth::forgetGuards();
        $this->password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($this->password)]);
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $this->password])->assertOk();
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

    private function membership(int $workspace, int $user): Membership
    {
        return Membership::query()->where('workspace_id', $workspace)->where('user_id', $user)->firstOrFail();
    }

    private function roles(int $user): array
    {
        return app(Concierge::class)->roleNames($this->membership(1, $user)->fresh());
    }

    private function transfer(int $to, ?string $password = null): TestResponse
    {
        return $this->putJson('/@alpha/settings/people/ownership', ['membership' => $this->membership(1, $to)->id, 'password' => $password ?? $this->password]);
    }

    public function test_the_owner_passes_ownership_and_becomes_an_admin(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        // The page offers it to the owner, with the other verified members to choose from.
        $page = $this->get('/@alpha/settings/people', $this->inertia())->assertOk()->assertJsonPath('props.can_transfer', true)->assertJsonPath('props.transfer_url', '/settings/people/ownership')->json('props');
        $this->assertSame([false, true], array_column($page['members'], 'transferable'));
        $this->transfer(3)->assertOk()->assertJsonPath('owner_membership_id', $this->membership(1, 3)->id);
        $this->assertSame(['owner'], $this->roles(3));
        $this->assertSame(['admin'], $this->roles(1));
        $ownership = Ownership::query()->where('workspace_id', 1)->firstOrFail();
        $this->assertSame($this->membership(1, 3)->id, $ownership->membership_id);
        $this->assertSame($this->membership(1, 1)->id, $ownership->previous_membership_id);
        // The password confirmation does not linger: the same session cannot transfer again without it.
        $this->assertNull(session('auth.password_confirmed_at'));
        // The former owner is an admin now: no transfer, and the new owner is protected from them.
        $this->get('/@alpha/settings/people', $this->inertia())->assertJsonPath('props.can_transfer', false);
        $this->transfer(3)->assertForbidden();
        $this->deleteJson('/@alpha/settings/people/members/'.$this->membership(1, 3)->id)->assertForbidden();
        $this->signOut();
        $this->signIn(3);
        $this->get('/@alpha/settings/people', $this->inertia())->assertJsonPath('props.can_transfer', true);
        $this->deleteJson('/@alpha/settings/people/members/'.$this->membership(1, 1)->id)->assertOk();
    }

    public function test_the_password_must_be_confirmed(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->transfer(3, 'not-the-password')->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/@alpha/settings/people/ownership', ['membership' => $this->membership(1, 3)->id])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame(['owner'], $this->roles(1));
        $this->assertSame(['viewer'], $this->roles(3));
    }

    public function test_only_the_owner_may_transfer(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->putJson('/@alpha/settings/people/members/'.$this->membership(1, 3)->id, ['role' => 'admin'])->assertOk();
        $this->signOut();
        // An admin, who may manage viewers, still cannot take ownership; nor can a viewer.
        $this->signIn(3);
        $this->get('/@alpha/settings/people', $this->inertia())->assertJsonPath('props.can_transfer', false);
        $this->putJson('/@alpha/settings/people/ownership', ['membership' => $this->membership(1, 3)->id, 'password' => $this->password])->assertForbidden();
        $this->putJson('/@alpha/settings/people/ownership', ['membership' => $this->membership(1, 1)->id, 'password' => $this->password])->assertForbidden();
        $this->assertSame(['owner'], $this->roles(1));
        $this->assertSame(['admin'], $this->roles(3));
        $this->assertSame(1, Ownership::query()->where('workspace_id', 1)->count());
    }

    public function test_the_target_must_be_an_existing_active_member_of_this_workspace(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        // Yourself, a membership of another workspace, one that does not exist, and a member who has been removed.
        $this->putJson('/@alpha/settings/people/ownership', ['membership' => $this->membership(1, 1)->id, 'password' => $this->password])->assertForbidden();
        $this->putJson('/@alpha/settings/people/ownership', ['membership' => $this->membership(2, 2)->id, 'password' => $this->password])->assertNotFound();
        $this->putJson('/@alpha/settings/people/ownership', ['membership' => 99999, 'password' => $this->password])->assertNotFound();
        $this->deleteJson('/@alpha/settings/people/members/'.$this->membership(1, 3)->id)->assertOk();
        $this->putJson('/@alpha/settings/people/ownership', ['membership' => $this->membership(1, 3)->id, 'password' => $this->password])->assertForbidden();
        $this->assertSame(['owner'], $this->roles(1));
        $this->assertSame(1, DB::table('concierge_ownerships')->where('workspace_id', 1)->count());
    }
}
