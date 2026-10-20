<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';

/** The shell's mark is the platform's identity (APP_NAME and a logomark); a workspace's name is only ever in the switcher. */
final class MarkTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
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
        $this->get('/notes', $inertia)->assertOk()
            ->assertJsonPath('props.layout.product.name', 'Platform Name')->assertJsonPath('props.layout.product.logomark', '/logomark.svg')
            ->assertJsonPath('props.layout.switcher.current.name', $workspace);
        // With two workspaces the workspace is in the switcher; the mark is unchanged.
        $invite = InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']));
        AcceptInvitation::run(User::findOrFail(1), $invite->token);
        $page = $this->get('/@alpha/notes', $inertia)->assertOk()->assertJsonPath('props.layout.product.name', 'Platform Name')->json('props.layout');
        $this->assertSame($workspace, $page['switcher']['current']['name']);
        $this->assertNotContains($workspace, [$page['product']['name']]);
        // The kit points at a logomark that the starter ships.
        $this->assertFileExists(public_path('logomark.svg'));
        // The shell renders the product, never the switcher's workspace, in the mark.
        $layout = file_get_contents(base_path('resources/js/pages/Workspace/Layout.vue'));
        $this->assertMatchesRegularExpression('/<slot name="mark">.*product\.name.*<\/slot>/s', $layout);
        $this->assertDoesNotMatchRegularExpression('/<slot name="mark">[^<]*<[^>]*>[^<]*switcher/s', $layout);
    }
}
