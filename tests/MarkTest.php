<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\DefaultBrand;
use Tests\TestCase;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';
require_once __DIR__.'/Concerns/DefaultBrand.php';

/** The shell's mark is the platform's identity (APP_NAME and a logomark); a workspace's name is only ever in the switcher. */
final class MarkTest extends TestCase
{
    use DefaultBrand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultBrand();
    }

    private function signIn(int $id): array
    {
        $password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();

        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    public function test_the_mark_is_app_name_and_a_logomark_never_the_workspace_name(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        config(['app.name' => 'Platform Name']);
        $inertia = $this->signIn(1);
        $workspace = Workspace::findOrFail(1)->name;
        $this->assertNotSame('Platform Name', $workspace);
        $this->get('/dashboard', $inertia)->assertOk()
            ->assertJsonPath('props.shell.product.name', 'Platform Name')->assertJsonPath('props.shell.product.logomark', '/logomark.svg')
            ->assertJsonPath('props.shell.workspace.name', $workspace)->assertJsonCount(1, 'props.shell.switcher.choices');
        // With two workspaces the switcher lists both; the mark is unchanged.
        $invite = InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']));
        AcceptInvitation::run(User::findOrFail(1), $invite->token);
        $page = $this->get('/@alpha/dashboard', $inertia)->assertOk()->assertJsonPath('props.shell.product.name', 'Platform Name')->json('props.shell');
        $this->assertSame($workspace, $page['switcher']['current']['name']);
        $this->assertNotContains($workspace, [$page['product']['name']]);
        // The kit points at a logomark that the starter ships.
        $this->assertFileExists(public_path('logomark.svg'));
        // The shell renders the product, never the switcher's workspace, in the mark.
        $layout = file_get_contents(base_path('resources/js/pages/Workspace/Layout.vue'));
        $this->assertMatchesRegularExpression('/<slot name="mark">.*product\.name.*<\/slot>/s', $layout);
        $this->assertDoesNotMatchRegularExpression('/<slot name="mark">[^<]*<[^>]*>[^<]*switcher/s', $layout);
    }

    public function test_the_shell_always_names_the_workspace_and_always_has_the_switcher(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $inertia = $this->signIn(1);
        $workspace = Workspace::findOrFail(1)->name;
        // One workspace: its name, its link and a settings action the viewer may use (the owner), and the switcher with its one workspace.
        $one = $this->get('/dashboard', $inertia)->assertOk()->json('props.shell');
        $this->assertSame([1], array_column($one['switcher']['choices'], 'id'), 'the switcher is always there, with one workspace too');
        $this->assertSame([1, $workspace, '/dashboard'], [$one['workspace']['id'], $one['workspace']['name'], $one['workspace']['url']]);
        $this->assertSame(['Workspace settings', 'get'], [$one['workspace']['settings']['label'], $one['workspace']['settings']['method']]);
        $this->assertStringEndsWith('/settings', $one['workspace']['settings']['url']);
        $this->assertSame(['All workspaces', '/me/workspaces'], [$one['workspace']['all']['label'], $one['workspace']['all']['url']], 'the chooser link is the server\'s, with one workspace too');
        // Two: the switcher carries both, the bar still names the current one.
        AcceptInvitation::run(User::findOrFail(1), InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']))->token);
        $two = $this->get('/@alpha/dashboard', $inertia)->assertOk()->json('props.shell');
        $this->assertSame([1, 2], array_column($two['switcher']['choices'], 'id'));
        $this->assertSame($workspace, $two['workspace']['name']);
        $this->assertSame(['All workspaces', '/me/workspaces', 'get'], [$two['workspace']['all']['label'], $two['workspace']['all']['url'], $two['workspace']['all']['method']]);
        // A viewer can still open General (read-only), so the action is there for them too; it is only ever absent, never disabled.
        $this->postJson('/logout')->assertNoContent();
        $this->flushSession();
        $this->defaultCookies = [];
        Auth::forgetGuards();
        $viewer = $this->signIn(3);
        $this->assertSame(['Workspace settings', false], [$this->get('/dashboard', $viewer)->assertOk()->json('props.shell.workspace.settings.label'), $this->get('/dashboard', $viewer)->json('props.shell.workspace.settings.disabled')]);
    }
}
