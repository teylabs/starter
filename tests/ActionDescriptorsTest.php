<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Models\Membership;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/** Guard 4: no `can` maps. Pages carry action descriptors (null when denied, disabled with a reason when shown but unusable), never permission or role flags. */
final class ActionDescriptorsTest extends TestCase
{
    private string $password = '';

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

    private const BANNED = ['can', 'permissions', 'abilities', 'isAdmin', 'is_admin'];

    private function walk(mixed $value, string $path, array &$found): void
    {
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $key => $child) {
            if (is_string($key) && (in_array($key, self::BANNED, true) || str_starts_with($key, 'can_') || in_array($key, ['manageable', 'is_owner'], true))) {
                $found[] = $path.'.'.$key;
            }
            $this->walk($child, $path.'.'.$key, $found);
        }
    }

    public function test_no_response_carries_a_can_map_or_permission_flag_and_denied_actions_are_null_or_explained(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(0, app(Kernel::class)->call('kit:permissions:sync', ['--no-interaction' => true]));
        $pages = ['/@alpha/dashboard', '/@alpha/settings', '/@alpha/settings/general', '/@alpha/settings/people', '/@alpha/settings/connections', '/me', '/me/profile', '/me/workspaces'];
        $found = [];
        // The guest pages, then the owner and the viewer.
        foreach (['/login'] as $url) {
            $this->walk($this->get($url, $this->inertia())->json('props'), $url, $found);
        }
        $this->signIn(1);
        foreach ($pages as $url) {
            $response = $this->get($url, $this->inertia());
            if ($response->getStatusCode() === 200) {
                $this->walk($response->json('props'), $url, $found);
            }
        }
        $owner = $this->get('/@alpha/settings/general', $this->inertia())->json('props');
        $this->assertSame(['Save', 'put', false], [$owner['save']['label'], $owner['save']['method'], $owner['save']['disabled']]);
        // Role-denied is null, never disabled: General has no danger zone at all, Make owner is a row action for the owner, and the owner has no Leave.
        $this->assertArrayNotHasKey('transfer', $owner);
        $this->assertArrayNotHasKey('leave', $owner);
        $this->assertNull($this->get('/@alpha/dashboard', $this->inertia())->json('props.shell.workspace.leave'));
        $rows = $this->get('/@alpha/settings/people', $this->inertia())->json('props.table.rows');
        $this->assertSame([[], ['change-role', 'transfer-ownership', 'remove']], array_map(fn (array $row) => array_column($row['actions'], 'key'), $rows));
        $this->signOut();
        $this->signIn(3);
        foreach ($pages as $url) {
            $response = $this->get($url, $this->inertia());
            if ($response->getStatusCode() === 200) {
                $this->walk($response->json('props'), $url, $found);
            }
        }
        $viewer = $this->get('/@alpha/settings/general', $this->inertia())->assertOk()->json('props');
        $this->assertNull($viewer['save'], 'a viewer whose role can only read has no Save: the page is read-only, nothing is greyed out');
        $leave = $this->get('/@alpha/dashboard', $this->inertia())->json('props.shell.workspace.leave');
        $this->assertFalse($leave['disabled']);
        $this->assertSame('delete', $leave['method']);
        $this->assertSame([], $found, 'no can map, can_* flag, permissions list or role flag in any page or shared prop');
    }

    public function test_the_guest_shell_response_matches_its_generated_type_and_has_no_shell_only_field(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $types = (string) file_get_contents(dirname(__DIR__).'/resources/js/types.generated.d.ts');
        $this->assertSame(1, preg_match('/export type GuestShellData = \{(.*?)\n\};/s', $types, $guest));
        $declared = [[], []];
        $depth = 0;
        foreach (explode("\n", $guest[1]) as $line) {
            if ($depth === 0 && preg_match('/^([a-z_]+):/', $line, $key)) {
                $declared[1][] = $key[1];
            }
            $depth += substr_count($line, '{') - substr_count($line, '}');
        }
        $sent = array_keys($this->get('/login', $this->inertia())->json('props.shell'));
        sort($sent);
        $declaredKeys = $declared[1];
        sort($declaredKeys);
        $this->assertSame($declaredKeys, $sent, 'the guest shell sends exactly what GuestShellData declares');
        foreach (['user', 'navigation', 'switcher', 'links', 'env', 'can'] as $field) {
            $this->assertNotContains($field, $sent, $field.' belongs to ShellData only');
        }
    }

    public function test_an_admin_variant_has_no_owner_actions_and_every_row_action_check_is_batched(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(0, app(Kernel::class)->call('kit:permissions:sync', ['--no-interaction' => true]));
        $this->signIn(1);
        $this->putJson('/@alpha/settings/people/members/'.Membership::query()->where('workspace_id', 1)->where('user_id', 3)->firstOrFail()->id, ['role' => 'admin'])->assertOk();
        $this->signOut();
        $this->signIn(3);
        $found = [];
        foreach (['/@alpha/dashboard', '/@alpha/settings', '/@alpha/settings/general', '/@alpha/settings/people', '/me/workspaces'] as $url) {
            $response = $this->get($url, $this->inertia());
            if ($response->getStatusCode() === 200) {
                $this->walk($response->json('props'), $url, $found);
            }
        }
        $this->assertSame([], $found);
        $general = $this->get('/@alpha/settings/general', $this->inertia())->assertOk()->json('props');
        $this->assertFalse($general['save']['disabled'], 'an admin may change the settings');
        $this->assertArrayNotHasKey('transfer', $general);
        $leave = $this->get('/@alpha/dashboard', $this->inertia())->json('props.shell.workspace.leave');
        $this->assertSame(['delete', false], [$leave['method'], $leave['disabled']], 'an admin can leave');
        $people = $this->get('/@alpha/settings/people', $this->inertia())->assertOk()->json('props');
        $this->assertSame('Invite member', $people['invite']['label']);
        $this->assertSame([[], []], array_column($people['table']['rows'], 'actions'), 'no action on the owner or on themselves');
        // The descriptors for a page of rows come from batched reads: ten more members add no queries per row.
        $queries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/@alpha/settings/people', $this->inertia())->assertOk();

            return count(DB::getQueryLog());
        };
        $before = $queries();
        foreach (range(10, 19) as $id) {
            User::create(['id' => $id, 'name' => 'Person '.$id, 'email' => 'person'.$id.'@example.test', 'email_verified_at' => now()]);
            AcceptInvitation::run(User::findOrFail($id), InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), 'person'.$id.'@example.test', new GrantSet(['viewer']))->token);
        }
        $this->assertSame($before, $queries(), 'more rows, no more queries');
        $this->assertCount(12, $this->getJson('/@alpha/settings/people')->json('table.rows'));
    }
}
