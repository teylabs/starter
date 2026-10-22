<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\DefaultBrand;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require_once __DIR__.'/Concerns/DefaultBrand.php';
uses(DefaultBrand::class);

beforeEach(function () {
    $this->defaultBrand();
});
function markSignIn(int $id): array
{
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();

    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
test('the mark is app name and a logomark never the workspace name', function () {
    require dirname(__DIR__).'/database/fixture.php';
    config(['app.name' => 'Platform Name']);
    $inertia = markSignIn(1);
    $workspace = Workspace::findOrFail(1)->name;
    $this->assertNotSame('Platform Name', $workspace);
    $this->get('/dashboard', $inertia)->assertOk()
        ->assertJsonPath('props.shell.product.name', 'Platform Name')->assertJsonPath('props.shell.product.logomark', '/logomark.svg')
        ->assertJsonPath('props.shell.workspace.name', $workspace)->assertJsonCount(1, 'props.shell.switcher.choices');

    // With two workspaces the switcher lists both; the mark is unchanged.
    $invite = InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']));
    AcceptInvitation::run(User::findOrFail(1), $invite->token);
    $page = $this->get('/@alpha/dashboard', $inertia)->assertOk()->assertJsonPath('props.shell.product.name', 'Platform Name')->json('props.shell');
    expect($page['switcher']['current']['name'])->toBe($workspace);
    expect([$page['product']['name']])->not->toContain($workspace);

    // The kit points at a logomark that the starter ships.
    expect(public_path('logomark.svg'))->toBeFile();

    // The shell renders the product, never the switcher's workspace, in the mark.
    $layout = file_get_contents(base_path('resources/js/pages/Workspace/Layout.vue'));
    expect($layout)->toMatch('/<slot name="mark">.*product\.name.*<\/slot>/s');
    $this->assertDoesNotMatchRegularExpression('/<slot name="mark">[^<]*<[^>]*>[^<]*switcher/s', $layout);
});
test('the shell always names the workspace and always has the switcher', function () {
    require dirname(__DIR__).'/database/fixture.php';
    $inertia = markSignIn(1);
    $workspace = Workspace::findOrFail(1)->name;

    // One workspace: its name, its link and a settings action the viewer may use (the owner), and the switcher with its one workspace.
    $one = $this->get('/dashboard', $inertia)->assertOk()->json('props.shell');
    expect(array_column($one['switcher']['choices'], 'id'))->toBe([1], 'the switcher is always there, with one workspace too');
    expect([$one['workspace']['id'], $one['workspace']['name'], $one['workspace']['url']])->toBe([1, $workspace, '/dashboard']);
    expect([$one['workspace']['settings']['label'], $one['workspace']['settings']['method']])->toBe(['Workspace settings', 'get']);
    expect($one['workspace']['settings']['url'])->toEndWith('/settings');
    expect([$one['workspace']['all']['label'], $one['workspace']['all']['url']])->toBe(['All workspaces', '/me/workspaces'], 'the chooser link is the server\'s, with one workspace too');

    // Two: the switcher carries both, the bar still names the current one.
    AcceptInvitation::run(User::findOrFail(1), InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']))->token);
    $two = $this->get('/@alpha/dashboard', $inertia)->assertOk()->json('props.shell');
    expect(array_column($two['switcher']['choices'], 'id'))->toBe([1, 2]);
    expect($two['workspace']['name'])->toBe($workspace);
    expect([$two['workspace']['all']['label'], $two['workspace']['all']['url'], $two['workspace']['all']['method']])->toBe(['All workspaces', '/me/workspaces', 'get']);

    // A viewer can still open General (read-only), so the action is there for them too; it is only ever absent, never disabled.
    $this->postJson('/logout')->assertNoContent();
    $this->flushSession();
    $this->defaultCookies = [];
    Auth::forgetGuards();
    $viewer = markSignIn(3);
    expect([$this->get('/dashboard', $viewer)->assertOk()->json('props.shell.workspace.settings.label'), $this->get('/dashboard', $viewer)->json('props.shell.workspace.settings.disabled')])->toBe(['Workspace settings', false]);
});
