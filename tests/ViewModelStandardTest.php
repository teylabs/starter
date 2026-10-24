<?php

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Kit\Features\Access\Permissions;
use Tey\Kit\Features\Core\ViewModels\AdHocViewModel;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\Core\ViewModels\ViewModel;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Models\Workspace;

beforeEach(function () {
    config(['app.name' => 'Product name']);
});
function viewModelStandardInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function viewModelStandardSignIn(int $id): void
{
    Auth::forgetGuards();
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
/** @return list<string> */
function viewModelStandardSources(string $directory, string $extension): array
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
function viewModelStandardPageViewModels(): array
{
    $classes = [];
    foreach (viewModelStandardSources(base_path('vendor/tey/kit/src/Features'), 'php') as $file) {
        if (str_contains($file, '/ViewModels/') && preg_match('/namespace ([^;]+);.*?final class (\w+) extends PageViewModel/s', (string) file_get_contents($file), $m)) {
            $classes[] = $m[1].'\\'.$m[2];
        }
    }
    sort($classes);

    return $classes;
}
test('page contract every render site passes a view model and data is never the page', function () {
    $sites = 0;
    foreach ([...viewModelStandardSources(base_path('vendor/tey/kit/src/Features'), 'php'), ...viewModelStandardSources(base_path('app'), 'php'), base_path('tests/Fixtures/RecordsFixture.php')] as $file) {
        $source = (string) file_get_contents($file);
        if (! str_contains($source, 'Inertia::render(')) {
            continue;
        }
        preg_match_all('/Inertia::render\(([^;]*?)\)(?:\)|;|\))/s', $source, $calls);
        foreach (preg_match_all('/Inertia::render\(([^\n]*)/', $source, $lines) ? $lines[1] : [] as $call) {
            $sites++;
            $viaVariable = preg_match('/, \$(model|viewModel)\b/', $call) === 1;
            expect(str_contains($call, 'ViewModel(') || str_contains($call, 'ViewModel::') || $viaVariable || str_contains($call, 'new self('))->toBeTrue(basename($file).' renders a page with something that is not a ViewModel: '.trim($call));
            if ($viaVariable) {
                expect($source)->toMatch('/\$(model|viewModel) = (new \w*ViewModel|AdHocViewModel::make)\(/', basename($file).' builds its $model from a ViewModel');
            }
        }
    }
    expect($sites)->toBeGreaterThanOrEqual(14);

    // A class named ...ViewModel under a ViewModels folder is the typed base, not a laravel-data object.
    foreach (viewModelStandardSources(base_path('vendor/tey/kit/src/Features'), 'php') as $file) {
        if (str_contains($file, '/ViewModels/') && ! str_ends_with($file, 'PlainPageLayouts.php') && ! str_contains($file, '/Core/ViewModels/') && preg_match('/class \w+ViewModel extends (\w+)/', (string) file_get_contents($file), $m)) {
            // The modal pages' ViewModels (Invite member, Change role) are plain ViewModels: a modal is not a page and has no layout.
            expect(['PageViewModel', 'ViewModel'])->toContain($m[1]);
        }
    }

    // Pages declare no layout of their own, and there is no root-folder fallback layout.
    foreach (viewModelStandardSources(resource_path('js/pages'), 'vue') as $file) {
        $source = (string) file_get_contents($file);
        $this->assertDoesNotMatchRegularExpression('/defineOptions\(\{[^}]*layout|\blayout\s*:\s*[A-Z\[]|setLayoutProps/', $source, basename(dirname($file)).'/'.basename($file).' declares a layout');
    }
    $this->assertFileDoesNotExist(resource_path('js/pages/Layout.vue'));

    // The producers: no `layout` share (the page's), `shell` is the one share, and the old shares are gone.
    $middleware = (string) file_get_contents(base_path('vendor/tey/kit/src/Features/SessionUi/Http/Middleware/HandleInertiaRequests.php'));
    expect(preg_match_all("/'shell' =>/", $middleware))->toBe(1);
    $this->assertDoesNotMatchRegularExpression("/'(layout|document_title|settings_nav|can|breadcrumbs)' =>/", $middleware);

    // The persistent sublayouts are the nearest folder's Layout.vue, in the settings and account resources.
    expect(resource_path('js/pages/Workspace/Settings/Layout.vue'))->toBeFile();
    expect(resource_path('js/pages/Workspace/Account/Layout.vue'))->toBeFile();
    $this->assertStringContainsString('nearestPageLayout', (string) file_get_contents(resource_path('js/app.ts')));
});
test('ad hoc view models are for small pages and graduate to a class', function () {
    // The builder: keys are unique, `layout` and `shell` are reserved, and the layout stays typed.
    $model = AdHocViewModel::make()->title('Reports')->with('stats', ['a' => 1])->with(['period' => 'week'])->optional('export', fn () => [])->defer('chart', fn () => []);
    expect($model->propCount())->toBe(4);
    expect(array_keys($model->toArray()))->toBe(['stats', 'period', 'export', 'chart', 'layout']);
    expect($model->toArray()['layout']->title)->toBe('Reports');
    expect($model->toArray()['layout']->documentTitle)->toBe('Reports · Product name');
    foreach ([fn () => AdHocViewModel::make()->with('layout', 1), fn () => AdHocViewModel::make()->with('shell', 1), fn () => AdHocViewModel::make()->with('a', 1)->with('a', 2)] as $bad) {
        try {
            $bad();
            $this->fail('a reserved or duplicate key must throw');
        } catch (InvalidArgumentException) {
            expect(true)->toBeTrue();
        }
    }

    // The guard counts ad-hoc render sites: at most three props, no action or permission prop, one render site, no nested ViewModel.
    $sites = [];
    foreach ([...viewModelStandardSources(base_path('vendor/tey/kit/src/Features'), 'php'), ...viewModelStandardSources(base_path('app'), 'php'), base_path('tests/Fixtures/RecordsFixture.php')] as $file) {
        $source = (string) file_get_contents($file);
        preg_match_all('/AdHocViewModel::make\(\)((?:->[a-z]+\([^;]*?\))+)/s', $source, $chains);
        foreach ($chains[1] as $chain) {
            $sites[] = $chain;
            $keys = preg_match_all("/->(?:with|optional|defer)\\((?:'(\\w+)'|\\[)/", $chain, $named) + (preg_match_all("/'(\\w+)' =>/", $chain) ?: 0);
            expect($keys)->toBeLessThanOrEqual(3, basename($file).' has an ad-hoc page with more than three props: make it a ViewModel class');
            $this->assertDoesNotMatchRegularExpression("/'(actions?|can_?\\w*|abilities)'/", $chain, basename($file).' has an action or permission prop in an ad-hoc page');
            $this->assertDoesNotMatchRegularExpression('/new \w+ViewModel\(/', $chain, basename($file).' nests a ViewModel in an ad-hoc page');
        }
    }
    expect($sites)->not->toBeEmpty();
    expect(count(array_unique($sites)))->toBe(count($sites), 'an ad-hoc page is rendered from one place');
});
test('serializer parity the prop rule matches the payload and the generated types', function () {
    $generated = (string) file_get_contents(resource_path('js/types.generated.d.ts'));
    $classes = viewModelStandardPageViewModels();
    expect(count($classes))->toBeGreaterThanOrEqual(13);
    foreach ($classes as $class) {
        $props = ViewModel::propMethods($class);
        expect($props)->toContain('title');
        expect($props)->toContain('layout');
        // Every prop method declares a return type, and the generated TypeScript has exactly those properties.
        foreach ($props as $method) {
            expect((new ReflectionMethod($class, $method))->getReturnType())->not->toBeNull($class.'::'.$method.' has no return type');
        }
        preg_match('/export type '.preg_quote(class_basename($class), '/').' = \{(.*?)\n\};/s', $generated, $block);
        expect($block)->not->toBeEmpty(class_basename($class).' is generated');
        preg_match_all('/^readonly (\w+)\??:/m', $block[1], $keys);
        expect($keys[1])->toBe($props, class_basename($class).': the generated type must list the prop methods, in order');
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
    expect($vm->toArray())->toBe(['mode' => 'on', 'nothing' => null, 'nested' => ['a' => 1]]);
});
test('http freshness each visit carries its own navigation heading title and trail', function () {
    require dirname(__DIR__).'/database/fixture.php';
    viewModelStandardSignIn(1);
    $visit = fn (string $path): array => $this->get($path, viewModelStandardInertia())->assertOk()->json('props.layout');
    $settings = $visit('/@alpha/settings/general');
    expect(collect($settings['navigation'][0]['items'])->firstWhere('current', true)['label'])->toBe('General');
    expect($settings['title'])->toBe('Workspace settings');
    expect($settings['documentTitle'])->toBe('General · Settings · Product name');
    expect(array_column($settings['breadcrumbs'], 'label'))->toBe(['Product name', 'Settings', 'General']);

    // Moving to a sibling changes the current item, the heading, the document title and the trail; a page with none clears the navigation and the trail's tail.
    $people = $visit('/@alpha/settings/people');
    expect(collect($people['navigation'][0]['items'])->firstWhere('current', true)['label'])->toBe('People');
    expect($people['title'])->toBe('People');
    expect(array_column($people['breadcrumbs'], 'label'))->toBe(['Product name', 'Settings', 'People']);
    $dashboard = $visit('/@alpha');
    expect($dashboard['navigation'])->toBe([]);
    expect(array_column($dashboard['breadcrumbs'], 'label'))->toBe(['Product name', 'Dashboard']);

    // The account pages carry the personal navigation, and `shell` is the one shared prop of the app shell.
    $security = $visit('/me/security');
    expect(collect($security['navigation'][0]['items'])->firstWhere('current', true)['label'])->toBe('Security');
    $page = $this->get('/me/security', viewModelStandardInertia())->json();
    expect($page['sharedProps'])->toBe(['errors', 'shell']);
    $this->assertArrayNotHasKey('can', $page['props']);
    $this->assertArrayNotHasKey('breadcrumbs', $page['props']);
    $this->assertArrayNotHasKey('document_title', $page['props']);
    $this->assertArrayNotHasKey('settings_nav', $page['props']);

    // A partial reload names the props it wants: the client keeps `shell` (and the layout) it already has, for the same viewer and workspace.
    $partial = $this->get('/@alpha/settings/people', [...viewModelStandardInertia(), 'X-Inertia-Partial-Component' => 'Workspace/Settings/People/Page', 'X-Inertia-Partial-Data' => 'table'])->assertOk()->json('props');
    expect($partial)->toHaveKey('table');
    $this->assertArrayNotHasKey('shell', $partial);
});
test('the shell and layout run within a query budget and checks run once per request', function () {
    require dirname(__DIR__).'/database/fixture.php';
    viewModelStandardSignIn(1);
    $queries = function (string $path): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($path, viewModelStandardInertia())->assertOk();

        return count(DB::getQueryLog());
    };
    $this->get('/@alpha', viewModelStandardInertia())->assertOk();
    $before = $queries('/@alpha');

    // Workspaces the viewer does not belong to add nothing: the shell reads the viewer's memberships, never every workspace.
    foreach (range(10, 40) as $id) {
        Workspace::query()->forceCreate(['id' => $id, 'name' => 'Other '.$id, 'slug' => 'other-'.$id, 'admitted' => true]);
    }
    expect($queries('/@alpha'))->toBe($before, 'more workspaces, no more queries');

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
    expect(count(DB::getQueryLog()))->toBe(0, 'the second check is answered from the request');
});

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
