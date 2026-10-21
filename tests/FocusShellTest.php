<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tey\Kit\Features\SessionUi\FocusStep;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\PageShell;

require dirname(__DIR__).'/vendor/autoload.php';

/** The third shell: a signed-in page outside a workspace renders in the FocusShell, which carries its own fields and none of the app shell's. */
final class FocusShellTest extends TestCase
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

    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    private function signIn(int $id): void
    {
        Auth::forgetGuards();
        $password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    }

    public function test_each_viewer_gets_the_shell_the_server_picks_and_only_its_fields(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(0, app(Kernel::class)->call('kit:permissions:sync', ['--no-interaction' => true]));
        $token = str_repeat('a', 40);

        // A guest on an invitation link: the guest shell.
        $guest = $this->get('/invitations/'.$token, $this->inertia())->assertOk()->json('props.shell');
        $this->assertSame('guest', $guest['kind']);
        $this->assertSame(['footer', 'kind', 'product'], array_keys(Arr::sortRecursive($guest)));

        $this->signIn(1);
        // The same link for a signed-in viewer: the FocusShell, and nothing of the app shell or the guest shell.
        $focus = $this->get('/invitations/'.$token, $this->inertia())->assertOk()->json('props.shell');
        $this->assertSame('focus', $focus['kind']);
        $this->assertSame(['kind', 'links', 'product', 'step', 'user'], array_keys(Arr::sortRecursive($focus)));
        $this->assertSame(['sign_out' => '/logout', 'switch_account' => '/switch-account'], $focus['links']);
        $this->assertSame(['avatar_url', 'avatar_url_2x', 'email', 'initials', 'name'], array_keys(Arr::sortRecursive($focus['user'])));
        $this->assertSame('actor1@example.test', $focus['user']['email']);
        $this->assertNull($focus['step']);
        $this->assertSame(config('app.name'), $focus['product']['name']);
        foreach (['switcher', 'navigation', 'workspace', 'footer', 'account', 'breadcrumbs'] as $appOnly) {
            $this->assertArrayNotHasKey($appOnly, $focus);
        }

        // A page in a workspace stays the app shell.
        $app = $this->get('/me/profile', $this->inertia())->assertOk()->json('props.shell');
        $this->assertSame('app', $app['kind']);
        $this->assertArrayHasKey('navigation', $app);
    }

    public function test_a_focus_page_reports_its_step_and_switch_account_ends_the_session(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        Route::middleware(['web', 'auth:web'])->get('/focus-probe', function (Request $request) {
            FocusStep::set($request, 2, 3, 'Your photo');

            return response()->json(app(PageShell::class)->forRequest($request)->toArray());
        })->name('focus.probe');
        $this->signIn(1);
        $shell = $this->getJson('/focus-probe')->assertOk()->json();
        $this->assertSame('focus', $shell['kind']);
        $this->assertSame(['current' => 2, 'total' => 3, 'label' => 'Your photo'], $shell['step']);

        $this->post('/switch-account', [], ['referer' => url('/invitations/'.str_repeat('a', 40))])->assertRedirect('/login');
        $this->assertGuest('web');
        $this->assertSame(url('/invitations/'.str_repeat('a', 40)), session('url.intended'));
    }
}
