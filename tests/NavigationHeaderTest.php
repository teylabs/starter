<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tey\Kit\Features\Core\Data\NavigationHeader;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/** The way up one level: every /me page escapes to the workspace the viewer was in, settings sections and top-level pages have none, and nothing is derived from the trail. */
final class NavigationHeaderTest extends TestCase
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

    public function test_account_pages_escape_to_the_workspace_and_other_pages_have_no_strip_unless_declared(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(0, app(Kernel::class)->call('kit:permissions:sync', ['--no-interaction' => true]));
        $this->signIn(1);
        $header = fn (string $url): ?array => $this->get($url, $this->inertia())->assertOk()->json('props.layout.navigationHeader');
        // Top level: the dashboard and the settings landing page sit directly under the home.
        $this->assertNull($header('/@alpha/dashboard'));
        $this->assertNull($header('/@alpha/settings'));
        // Settings sections have no strip; every /me page is an escape to the workspace the viewer was in; nothing is derived from the trail.
        foreach (['/@alpha/settings/general', '/@alpha/settings/people'] as $url) {
            $this->assertNull($header($url), $url);
        }
        $workspace = Workspace::findOrFail(1)->name;
        foreach (['/me', '/me/profile', '/me/password', '/me/security'] as $url) {
            $this->assertSame('Back to '.$workspace, $header($url)['label'], $url);
        }
        // The switcher names the workspace on an account page too: there is no account mode.
        $shell = $this->get('/me/profile', $this->inertia())->json('props.shell');
        $this->assertSame(Workspace::findOrFail(1)->name, $shell['workspace']['name']);
        $this->assertArrayNotHasKey('mode', $shell);
    }

    public function test_a_nested_resource_page_declares_its_parent_explicitly_through_its_view_model(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->get('/@alpha/dashboard', $this->inertia())->assertOk();
        $declared = new class extends PageViewModel
        {
            public function title(): string
            {
                return 'A member';
            }

            protected function navigationHeader(): ?NavigationHeader
            {
                return new NavigationHeader('Back to People', '/settings/people');
            }
        };
        $plain = new class extends PageViewModel
        {
            public function title(): string
            {
                return 'A page';
            }
        };
        $this->assertSame(['label' => 'Back to People', 'url' => '/settings/people'], $declared->layout()->navigationHeader?->toArray());
        $this->assertNull($plain->layout()->navigationHeader, 'nothing is derived: a page with no declaration has no strip');
    }
}
