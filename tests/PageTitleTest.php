<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Fixtures\RecordsFixtureProvider;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';

/** Every page's document title comes from its breadcrumb label: "{Page} · {Product}", server-rendered, never blank and never a URL. */
final class PageTitleTest extends TestCase
{
    public function createApplication()
    {
        require_once __DIR__.'/records_fixture.php';

        return starterAppWithRecords();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.name' => 'Product']);
    }

    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    private function signIn(int $id): void
    {
        $password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    }

    public function test_titles_are_the_breadcrumb_label_and_the_product(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $this->get('/login')->assertOk()->assertSee('<title inertia>Sign in · Product</title>', false);
        $this->signIn(1);
        foreach (['/dashboard' => 'Dashboard · Product', '/settings' => 'Settings · Product', '/records' => 'Records · Product', '/me' => 'Account · Product', '/settings/general' => 'General · Settings · Product', '/records/1' => 'Alpha private · Records · Product'] as $path => $title) {
            // The prop, for client-side visits, and the server-rendered <title>, so the first paint has it and no URL flashes.
            $this->get($path, $this->inertia())->assertOk()->assertJsonPath('props.document_title', $title);
            $this->get($path)->assertOk()->assertSee('<title inertia>'.e($title).'</title>', false);
        }
        // With several workspaces the workspace's name is added, before the product.
        $invite = InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), 'actor1@example.test', new GrantSet(['viewer']));
        AcceptInvitation::run(User::findOrFail(1), $invite->token);
        $this->get('/@alpha/records', $this->inertia())->assertJsonPath('props.document_title', 'Records · Alpha · Product');
        $this->get('/@alpha/settings/general', $this->inertia())->assertJsonPath('props.document_title', 'General · Settings · Alpha · Product');
        // An error names its page once: the heading and the title are the same label.
        $this->get('/@nobody/records', $this->inertia())->assertNotFound()->assertJsonPath('props.document_title', 'Page not found · Product')->assertJsonPath('props.title', 'Page not found');
    }

    public function test_no_page_has_a_blank_or_url_title(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        RecordsFixtureProvider::install();
        $this->signIn(1);
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (! in_array('GET', $route->methods(), true) || ! (str_starts_with($action, 'Tey\\Kit\\Features\\') || str_starts_with($action, 'Tests\\Fixtures\\')) || str_contains($action, 'Moved') || str_contains($route->uri(), '{') && ! str_contains($route->uri(), '{workspace}') && ! str_contains($route->uri(), '{record}')) {
                continue;
            }
            $path = '/'.preg_replace(['#\{workspace\}#', '#\{record\}#'], ['alpha', '1'], str_replace('@{workspace}', '@alpha', $route->uri()));
            $response = $this->get($path, $this->inertia());
            if ($response->isRedirect() || $response->getStatusCode() >= 500 || ! str_contains((string) $response->headers->get('Content-Type'), 'json')) {
                continue;
            }
            $title = (string) $response->json('props.document_title');
            $this->assertNotSame('', trim($title), $path.' has a blank title');
            $this->assertDoesNotMatchRegularExpression('#^(https?:|/)#', $title, $path.' has a URL for a title');
            $this->assertStringEndsWith('Product', $title, $path);
            $checked++;
        }
        $this->assertGreaterThanOrEqual(6, $checked);
    }
}
