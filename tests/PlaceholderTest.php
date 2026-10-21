<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\AssertsPlaceholder;
use Tests\TestCase;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\PlaceholderRoutes;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';
require_once __DIR__.'/Concerns/AssertsPlaceholder.php';

/** A page that is not built yet answers at the real page's address: honest, GET-only, behind the same sign-in, and a 404 (never a 403) for anyone without the ability. */
final class PlaceholderTest extends TestCase
{
    use AssertsPlaceholder;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.name' => 'Product name']);
        PlaceholderRoutes::reset();
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

    private function register(): void
    {
        $registry = app(Registry::class);
        $registry->placeholder('orders.index', 'orders', 'dashboard.read', 'Orders', 'Every order with its status and next step.', ['Orders'], 'full', 'the Orders release', [['label' => 'Back to the dashboard', 'route' => 'workspaces.dashboard.index'], ['label' => 'Manage people', 'route' => 'workspaces.people.index', 'ability' => 'members.manage']]);
        $registry->placeholder('billing.index', 'billing', 'members.manage', 'Billing', 'Plans and invoices.', ['Billing'], 'md');
        PlaceholderRoutes::register();
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_a_member_with_the_ability_gets_the_honest_page_in_the_real_pages_shell(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(3);
        $this->register();
        foreach (['/@alpha/orders', '/orders'] as $path) {
            $page = $this->get($path, $this->inertia())->assertOk();
            $page->assertJsonPath('component', 'Workspace/Placeholder/Page')->assertJsonPath('props.title', 'Orders')->assertJsonPath('props.description', 'Every order with its status and next step.')
                ->assertJsonPath('props.milestone', 'the Orders release')->assertJsonPath('props.width', 'full')->assertJsonPath('props.shell.kind', 'app')
                ->assertJsonPath('props.layout.documentTitle', 'Orders · Product name');
            $this->assertSame(['Orders'], array_slice(array_column($page->json('props.layout.breadcrumbs'), 'label'), -1));
            // Links are descriptors, and the one to a page this viewer may not open is simply not there.
            $this->assertSame(['Back to the dashboard'], array_column($page->json('props.links'), 'label'));
        }
        $this->assertPlaceholder('orders.index', '/@alpha/orders', $this->inertia());
        $this->assertPlaceholder('short.orders.index', '/orders', $this->inertia());
    }

    public function test_without_the_ability_it_is_a_404_like_a_stranger_and_a_guest_signs_in_first(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->register();
        $this->get('/@alpha/billing')->assertRedirect('/login');
        // A viewer lacks the billing ability: 404, not 403.
        $this->signIn(3);
        $this->get('/@alpha/billing', $this->inertia())->assertNotFound();
        $this->get('/billing', $this->inertia())->assertNotFound();
        // A person who belongs to no workspace gets the same 404 and learns nothing.
        $stranger = User::create(['id' => 77, 'name' => 'Stranger', 'email' => 'actor77@example.test', 'email_verified_at' => now()]);
        $this->assertNotNull($stranger);
        $this->postJson('/logout')->assertNoContent();
        $this->flushSession();
        $this->signIn(77);
        $this->get('/@alpha/orders', $this->inertia())->assertNotFound();
        // The owner (who may manage billing) gets the page, with its own width.
        $this->postJson('/logout')->assertNoContent();
        $this->flushSession();
        $this->signIn(1);
        $this->get('/@alpha/billing', $this->inertia())->assertOk()->assertJsonPath('props.width', 'md')->assertJsonPath('props.milestone', null);
    }

    public function test_a_placeholder_that_shares_a_route_name_with_a_real_page_or_breaks_the_rules_is_refused(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $registry = app(Registry::class);
        $registry->placeholder('workspaces.dashboard.index', 'forgotten', null, 'Dashboard', 'The real page landed and the line stayed.');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('shares its route name');
        PlaceholderRoutes::register();
    }

    public function test_the_registry_refuses_a_version_a_date_more_than_three_links_and_a_twin(): void
    {
        $registry = app(Registry::class);
        foreach ([['the 1.2 release', 'version'], ['Q4 2026', 'date']] as [$milestone, $what]) {
            try {
                $registry->placeholder('x.'.$what, 'x-'.$what, null, 'X', 'x', [], null, $milestone);
                $this->fail('a milestone with a '.$what.' is refused');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('words only', $e->getMessage());
            }
        }
        $link = ['label' => 'a', 'route' => 'workspaces.dashboard.index'];
        try {
            $registry->placeholder('x.links', 'x-links', null, 'X', 'x', [], null, null, [$link, $link, $link, $link]);
            $this->fail('four links are refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('at most three', $e->getMessage());
        }
        $registry->placeholder('x.twice', 'x-twice', null, 'X', 'x');
        $this->expectExceptionMessage('registered twice');
        $registry->placeholder('x.twice', 'x-twice-again', null, 'X', 'x');
    }

    public function test_a_planned_settings_section_is_tagged_listed_only_for_who_may_open_it_and_renders_in_the_settings_layout(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        app(Registry::class)->settingsSection('workspace', 'billing', 'Billing', 'Plans and invoices.', 'members.manage', 'Workspace', 30, 'credit-card', placeholder: true);
        PlaceholderRoutes::register();
        app('router')->getRoutes()->refreshNameLookups();
        $this->signIn(1);
        $sections = collect($this->get('/@alpha/settings', $this->inertia())->assertOk()->json('props.groups'))->flatMap(fn ($group) => $group['sections']);
        $this->assertSame('Planned', $sections->firstWhere('key', 'billing')['badge']);
        $this->assertNull($sections->firstWhere('key', 'general')['badge']);
        $page = $this->get('/@alpha/settings/billing', $this->inertia())->assertOk();
        $page->assertJsonPath('component', 'Workspace/Settings/Placeholder/Page')->assertJsonPath('props.width', null);
        $nav = collect($page->json('props.layout.navigation'))->flatMap(fn ($group) => $group['items']);
        $this->assertSame('Planned', $nav->firstWhere('label', 'Billing')['badge']);
        $this->assertPlaceholder('workspaces.settings.billing', '/@alpha/settings/billing', $this->inertia());
        // A viewer may not open it, so it is not listed and its address is a 404.
        $this->postJson('/logout')->assertNoContent();
        $this->flushSession();
        $this->signIn(3);
        $listed = collect($this->get('/@alpha/settings', $this->inertia())->json('props.groups'))->flatMap(fn ($group) => $group['sections'])->pluck('key')->all();
        $this->assertNotContains('billing', $listed);
        $this->get('/@alpha/settings/billing', $this->inertia())->assertNotFound();
    }
}
