<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Models\Membership;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

function actionDescriptorsInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function actionDescriptorsSignIn(int $id): void
{
    Auth::forgetGuards();
    test()->password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make(test()->password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => test()->password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
function actionDescriptorsSignOut(): void
{
    test()->postJson('/logout')->assertNoContent();
    test()->flushSession();
    test()->forgetCookies();
    Auth::forgetGuards();
}
const ACTION_DESCRIPTORS_BANNED = ['can', 'permissions', 'abilities', 'isAdmin', 'is_admin'];
function actionDescriptorsWalk(mixed $value, string $path, array &$found): void
{
    if (! is_array($value)) {
        return;
    }
    foreach ($value as $key => $child) {
        if (is_string($key) && (in_array($key, ACTION_DESCRIPTORS_BANNED, true) || str_starts_with($key, 'can_') || in_array($key, ['manageable', 'is_owner'], true))) {
            $found[] = $path.'.'.$key;
        }
        actionDescriptorsWalk($child, $path.'.'.$key, $found);
    }
}
test('no response carries a can map or permission flag and denied actions are null or explained', function () {
    require dirname(__DIR__).'/database/fixture.php';
    expect(app(Kernel::class)->call('kit:permissions:sync', ['--no-interaction' => true]))->toBe(0);
    $pages = ['/@alpha/dashboard', '/@alpha/settings', '/@alpha/settings/general', '/@alpha/settings/people', '/@alpha/settings/connections', '/me', '/me/profile', '/me/workspaces'];
    $found = [];

    // The guest pages, then the owner and the viewer.
    foreach (['/login'] as $url) {
        actionDescriptorsWalk($this->get($url, actionDescriptorsInertia())->json('props'), $url, $found);
    }
    actionDescriptorsSignIn(1);
    foreach ($pages as $url) {
        $response = $this->get($url, actionDescriptorsInertia());
        if ($response->getStatusCode() === 200) {
            actionDescriptorsWalk($response->json('props'), $url, $found);
        }
    }
    $owner = $this->get('/@alpha/settings/general', actionDescriptorsInertia())->json('props');
    expect([$owner['save']['label'], $owner['save']['method'], $owner['save']['disabled']])->toBe(['Save', 'put', false]);

    // Role-denied is null, never disabled: General has no danger zone at all, Make owner is a row action for the owner, and the owner has no Leave.
    $this->assertArrayNotHasKey('transfer', $owner);
    $this->assertArrayNotHasKey('leave', $owner);
    expect($this->get('/@alpha/dashboard', actionDescriptorsInertia())->json('props.shell.workspace.leave'))->toBeNull();
    $rows = $this->get('/@alpha/settings/people', actionDescriptorsInertia())->json('props.table.rows');
    expect(array_map(fn (array $row) => array_column($row['actions'], 'key'), $rows))->toBe([[], ['change-role', 'transfer-ownership', 'remove']]);
    actionDescriptorsSignOut();
    actionDescriptorsSignIn(3);
    foreach ($pages as $url) {
        $response = $this->get($url, actionDescriptorsInertia());
        if ($response->getStatusCode() === 200) {
            actionDescriptorsWalk($response->json('props'), $url, $found);
        }
    }
    $viewer = $this->get('/@alpha/settings/general', actionDescriptorsInertia())->assertOk()->json('props');
    expect($viewer['save'])->toBeNull('a viewer whose role can only read has no Save: the page is read-only, nothing is greyed out');
    $leave = $this->get('/@alpha/dashboard', actionDescriptorsInertia())->json('props.shell.workspace.leave');
    expect($leave['disabled'])->toBeFalse();
    expect($leave['method'])->toBe('delete');
    expect($found)->toBe([], 'no can map, can_* flag, permissions list or role flag in any page or shared prop');
});
test('the guest shell response matches its generated type and has no shell only field', function () {
    require dirname(__DIR__).'/database/fixture.php';
    $types = (string) file_get_contents(dirname(__DIR__).'/resources/js/types.generated.d.ts');
    expect(preg_match('/export type GuestShellData = \{(.*?)\n\};/s', $types, $guest))->toBe(1);
    $declared = [[], []];
    $depth = 0;
    foreach (explode("\n", $guest[1]) as $line) {
        if ($depth === 0 && preg_match('/^([a-z_]+):/', $line, $key)) {
            $declared[1][] = $key[1];
        }
        $depth += substr_count($line, '{') - substr_count($line, '}');
    }
    $sent = array_keys($this->get('/login', actionDescriptorsInertia())->json('props.shell'));
    sort($sent);
    $declaredKeys = $declared[1];
    sort($declaredKeys);
    expect($sent)->toBe($declaredKeys, 'the guest shell sends exactly what GuestShellData declares');
    foreach (['user', 'navigation', 'switcher', 'links', 'env', 'can'] as $field) {
        expect($sent)->not->toContain($field);
    }
});
test('an admin variant has no owner actions and every row action check is batched', function () {
    require dirname(__DIR__).'/database/fixture.php';
    expect(app(Kernel::class)->call('kit:permissions:sync', ['--no-interaction' => true]))->toBe(0);
    actionDescriptorsSignIn(1);
    $this->putJson('/@alpha/settings/people/members/'.Membership::query()->where('workspace_id', 1)->where('user_id', 3)->firstOrFail()->id, ['role' => 'admin'])->assertOk();
    actionDescriptorsSignOut();
    actionDescriptorsSignIn(3);
    $found = [];
    foreach (['/@alpha/dashboard', '/@alpha/settings', '/@alpha/settings/general', '/@alpha/settings/people', '/me/workspaces'] as $url) {
        $response = $this->get($url, actionDescriptorsInertia());
        if ($response->getStatusCode() === 200) {
            actionDescriptorsWalk($response->json('props'), $url, $found);
        }
    }
    expect($found)->toBe([]);
    $general = $this->get('/@alpha/settings/general', actionDescriptorsInertia())->assertOk()->json('props');
    expect($general['save']['disabled'])->toBeFalse('an admin may change the settings');
    $this->assertArrayNotHasKey('transfer', $general);
    $leave = $this->get('/@alpha/dashboard', actionDescriptorsInertia())->json('props.shell.workspace.leave');
    expect([$leave['method'], $leave['disabled']])->toBe(['delete', false], 'an admin can leave');
    $people = $this->get('/@alpha/settings/people', actionDescriptorsInertia())->assertOk()->json('props');
    expect($people['invite']['label'])->toBe('Invite member');
    expect(array_column($people['table']['rows'], 'actions'))->toBe([[], []], 'no action on the owner or on themselves');

    // The descriptors for a page of rows come from batched reads: ten more members add no queries per row.
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/@alpha/settings/people', actionDescriptorsInertia())->assertOk();

        return count(DB::getQueryLog());
    };
    $before = $queries();
    foreach (range(10, 19) as $id) {
        User::create(['id' => $id, 'name' => 'Person '.$id, 'email' => 'person'.$id.'@example.test', 'email_verified_at' => now()]);
        AcceptInvitation::run(User::findOrFail($id), InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), 'person'.$id.'@example.test', new GrantSet(['viewer']))->token);
    }
    expect($queries())->toBe($before, 'more rows, no more queries');
    expect($this->getJson('/@alpha/settings/people')->json('table.rows'))->toHaveCount(12);
});
