<?php

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tey\Kit\Features\Access\Permissions;
use Tey\Kit\Features\Core\ViewModels\AdHocViewModel;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\Core\ViewModels\ViewModel;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/**
 * The ViewModel standard: page props come from typed method ViewModels, the shell is one `shell` share, the page's `layout()` carries the section
 * navigation, heading, document title and breadcrumbs, and nothing else produces them. Four guards: the page contract, serializer parity, HTTP freshness
 * with a query budget, and (in tests/Browser/Account.php) that sibling sections keep one persistent sublayout.
 */
final class ViewModelStandardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.name' => 'Product name']);
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

    /** @return list<string> */
    private function sources(string $directory, string $extension): array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === $extension) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /** @return list<class-string<PageViewModel>> */
    private function pageViewModels(): array
    {
        $classes = [];
        foreach ($this->sources(base_path('vendor/tey/kit/src/Features'), 'php') as $file) {
            if (str_contains($file, '/ViewModels/') && preg_match('/namespace ([^;]+);.*?final class (\w+) extends PageViewModel/s', (string) file_get_contents($file), $m)) {
                $classes[] = $m[1].'\\'.$m[2];
            }
        }
        sort($classes);

        return $classes;
    }

    public function test_page_contract_every_render_site_passes_a_view_model_and_data_is_never_the_page(): void
    {
        $sites = 0;
        foreach ([...$this->sources(base_path('vendor/tey/kit/src/Features'), 'php'), ...$this->sources(base_path('app'), 'php'), base_path('tests/Fixtures/RecordsFixture.php')] as $file) {
            $source = (string) file_get_contents($file);
            if (! str_contains($source, 'Inertia::render(')) {
                continue;
            }
            preg_match_all('/Inertia::render\(([^;]*?)\)(?:\)|;|\))/s', $source, $calls);
            foreach (preg_match_all('/Inertia::render\(([^\n]*)/', $source, $lines) ? $lines[1] : [] as $call) {
                $sites++;
                $viaVariable = preg_match('/, \$(model|viewModel)\b/', $call) === 1;
                $this->assertTrue(str_contains($call, 'ViewModel(') || str_contains($call, 'ViewModel::') || $viaVariable || str_contains($call, 'new self('), basename($file).' renders a page with something that is not a ViewModel: '.trim($call));
                if ($viaVariable) {
                    $this->assertMatchesRegularExpression('/\$(model|viewModel) = (new \w*ViewModel|AdHocViewModel::make)\(/', $source, basename($file).' builds its $model from a ViewModel');
                }
            }
        }
        $this->assertGreaterThanOrEqual(14, $sites);
        // A class named ...ViewModel under a ViewModels folder is the typed base, not a laravel-data object.
        foreach ($this->sources(base_path('vendor/tey/kit/src/Features'), 'php') as $file) {
            if (str_contains($file, '/ViewModels/') && ! str_ends_with($file, 'PlainPageLayouts.php') && ! str_contains($file, '/Core/ViewModels/') && preg_match('/class \w+ViewModel extends (\w+)/', (string) file_get_contents($file), $m)) {
                // The modal pages' ViewModels (Invite member, Change role) are plain ViewModels: a modal is not a page and has no layout.
                $this->assertContains($m[1], ['PageViewModel', 'ViewModel'], basename($file).' is a ViewModel, not Data');
            }
        }
        // Pages declare no layout of their own, and there is no root-folder fallback layout.
        foreach ($this->sources(resource_path('js/pages'), 'vue') as $file) {
            $source = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/defineOptions\(\{[^}]*layout|\blayout\s*:\s*[A-Z\[]|setLayoutProps/', $source, basename(dirname($file)).'/'.basename($file).' declares a layout');
        }
        $this->assertFileDoesNotExist(resource_path('js/pages/Layout.vue'));
        // The producers: no `layout` share (the page's), `shell` is the one share, and the old shares are gone.
        $middleware = (string) file_get_contents(base_path('vendor/tey/kit/src/Features/SessionUi/Http/Middleware/HandleInertiaRequests.php'));
        $this->assertSame(1, preg_match_all("/'shell' =>/", $middleware));
        $this->assertDoesNotMatchRegularExpression("/'(layout|document_title|settings_nav|can|breadcrumbs)' =>/", $middleware);
        // The persistent sublayouts are the nearest folder's Layout.vue, in the settings and account resources.
        $this->assertFileExists(resource_path('js/pages/Workspace/Settings/Layout.vue'));
        $this->assertFileExists(resource_path('js/pages/Workspace/Account/Layout.vue'));
        $this->assertStringContainsString('nearestPageLayout', (string) file_get_contents(resource_path('js/app.ts')));
    }

    public function test_ad_hoc_view_models_are_for_small_pages_and_graduate_to_a_class(): void
    {
        // The builder: keys are unique, `layout` and `shell` are reserved, and the layout stays typed.
        $model = AdHocViewModel::make()->title('Reports')->with('stats', ['a' => 1])->with(['period' => 'week'])->optional('export', fn () => [])->defer('chart', fn () => []);
        $this->assertSame(4, $model->propCount());
        $this->assertSame(['stats', 'period', 'export', 'chart', 'layout'], array_keys($model->toArray()));
        $this->assertSame('Reports', $model->toArray()['layout']->title);
        $this->assertSame('Reports · Product name', $model->toArray()['layout']->documentTitle);
        foreach ([fn () => AdHocViewModel::make()->with('layout', 1), fn () => AdHocViewModel::make()->with('shell', 1), fn () => AdHocViewModel::make()->with('a', 1)->with('a', 2)] as $bad) {
            try {
                $bad();
                $this->fail('a reserved or duplicate key must throw');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        // The guard counts ad-hoc render sites: at most three props, no action or permission prop, one render site, no nested ViewModel.
        $sites = [];
        foreach ([...$this->sources(base_path('vendor/tey/kit/src/Features'), 'php'), ...$this->sources(base_path('app'), 'php'), base_path('tests/Fixtures/RecordsFixture.php')] as $file) {
            $source = (string) file_get_contents($file);
            preg_match_all('/AdHocViewModel::make\(\)((?:->[a-z]+\([^;]*?\))+)/s', $source, $chains);
            foreach ($chains[1] as $chain) {
                $sites[] = $chain;
                $keys = preg_match_all("/->(?:with|optional|defer)\\((?:'(\\w+)'|\\[)/", $chain, $named) + (preg_match_all("/'(\\w+)' =>/", $chain) ?: 0);
                $this->assertLessThanOrEqual(3, $keys, basename($file).' has an ad-hoc page with more than three props: make it a ViewModel class');
                $this->assertDoesNotMatchRegularExpression("/'(actions?|can_?\\w*|abilities)'/", $chain, basename($file).' has an action or permission prop in an ad-hoc page');
                $this->assertDoesNotMatchRegularExpression('/new \w+ViewModel\(/', $chain, basename($file).' nests a ViewModel in an ad-hoc page');
            }
        }
        $this->assertNotEmpty($sites);
        $this->assertSame(count($sites), count(array_unique($sites)), 'an ad-hoc page is rendered from one place');
    }

    public function test_serializer_parity_the_prop_rule_matches_the_payload_and_the_generated_types(): void
    {
        $generated = (string) file_get_contents(resource_path('js/types.generated.d.ts'));
        $classes = $this->pageViewModels();
        $this->assertGreaterThanOrEqual(13, count($classes));
        foreach ($classes as $class) {
            $props = ViewModel::propMethods($class);
            $this->assertContains('title', $props, $class);
            $this->assertContains('layout', $props, $class);
            // Every prop method declares a return type, and the generated TypeScript has exactly those properties.
            foreach ($props as $method) {
                $this->assertNotNull((new ReflectionMethod($class, $method))->getReturnType(), $class.'::'.$method.' has no return type');
            }
            preg_match('/export type '.preg_quote(class_basename($class), '/').' = \{(.*?)\n\};/s', $generated, $block);
            $this->assertNotEmpty($block, class_basename($class).' is generated');
            preg_match_all('/^readonly (\w+)\??:/m', $block[1], $keys);
            $this->assertSame($props, $keys[1], class_basename($class).': the generated type must list the prop methods, in order');
        }
        // A hidden method is not a prop, and an enum, a nested Arrayable and null come out as plain values.
        $vm = new class extends ViewModel
        {
            protected array $hidden = ['secret'];

            public function secret(): string
            {
                return 'x';
            }

            public function mode(): Status
            {
                return Status::On;
            }

            public function nothing(): ?array
            {
                return null;
            }

            public function nested(): PageLayoutForParity
            {
                return new PageLayoutForParity;
            }

            public function needsArgument(int $x): int
            {
                return $x;
            }

            protected function internal(): string
            {
                return 'x';
            }
        };
        $this->assertSame(['mode' => 'on', 'nothing' => null, 'nested' => ['a' => 1]], $vm->toArray());
    }

    public function test_http_freshness_each_visit_carries_its_own_navigation_heading_title_and_trail(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $visit = fn (string $path): array => $this->get($path, $this->inertia())->assertOk()->json('props.layout');
        $settings = $visit('/@alpha/settings/general');
        $this->assertSame('General', collect($settings['navigation'][0]['items'])->firstWhere('current', true)['label']);
        $this->assertSame('Workspace settings', $settings['title']);
        $this->assertSame('General · Settings · Product name', $settings['documentTitle']);
        $this->assertSame(['Product name', 'Settings', 'General'], array_column($settings['breadcrumbs'], 'label'));
        // Moving to a sibling changes the current item, the heading, the document title and the trail; a page with none clears the navigation and the trail's tail.
        $people = $visit('/@alpha/settings/people');
        $this->assertSame('People', collect($people['navigation'][0]['items'])->firstWhere('current', true)['label']);
        $this->assertSame('People', $people['title']);
        $this->assertSame(['Product name', 'Settings', 'People'], array_column($people['breadcrumbs'], 'label'));
        $dashboard = $visit('/@alpha/dashboard');
        $this->assertSame([], $dashboard['navigation']);
        $this->assertSame(['Product name', 'Dashboard'], array_column($dashboard['breadcrumbs'], 'label'));
        // The account pages carry the personal navigation, and `shell` is the one shared prop of the app shell.
        $security = $visit('/me/security');
        $this->assertSame('Security', collect($security['navigation'][0]['items'])->firstWhere('current', true)['label']);
        $page = $this->get('/me/security', $this->inertia())->json();
        $this->assertSame(['errors', 'shell'], $page['sharedProps']);
        $this->assertArrayNotHasKey('can', $page['props']);
        $this->assertArrayNotHasKey('breadcrumbs', $page['props']);
        $this->assertArrayNotHasKey('document_title', $page['props']);
        $this->assertArrayNotHasKey('settings_nav', $page['props']);
        // A partial reload names the props it wants: the client keeps `shell` (and the layout) it already has, for the same viewer and workspace.
        $partial = $this->get('/@alpha/settings/people', [...$this->inertia(), 'X-Inertia-Partial-Component' => 'Workspace/Settings/People/Page', 'X-Inertia-Partial-Data' => 'table'])->assertOk()->json('props');
        $this->assertArrayHasKey('table', $partial);
        $this->assertArrayNotHasKey('shell', $partial);
    }

    public function test_the_shell_and_layout_run_within_a_query_budget_and_checks_run_once_per_request(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $queries = function (string $path): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($path, $this->inertia())->assertOk();

            return count(DB::getQueryLog());
        };
        $this->get('/@alpha/dashboard', $this->inertia())->assertOk();
        $before = $queries('/@alpha/dashboard');
        // Workspaces the viewer does not belong to add nothing: the shell reads the viewer's memberships, never every workspace.
        foreach (range(10, 40) as $id) {
            Workspace::query()->forceCreate(['id' => $id, 'name' => 'Other '.$id, 'slug' => 'other-'.$id, 'admitted' => true]);
        }
        $this->assertSame($before, $queries('/@alpha/dashboard'), 'more workspaces, no more queries');
        // A policy or capability check is decided once per user, permission and workspace in a request.
        $permissions = app(Permissions::class);
        $permissions->remember();
        $workspace = Workspace::query()->findOrFail(1);
        $user = User::query()->findOrFail(1);
        $permissions->allows($user, 'workspace.members.manage', $workspace);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $permissions->allows($user, 'workspace.members.manage', $workspace);
        $permissions->granted($user, 'workspace.members.manage', $workspace);
        $this->assertSame(0, count(DB::getQueryLog()), 'the second check is answered from the request');
    }
}

enum Status: string
{
    case On = 'on';
}

final class PageLayoutForParity implements Arrayable
{
    public function toArray(): array
    {
        return ['a' => 1];
    }
}
