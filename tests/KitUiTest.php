<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';

/** Kit pages render UI only through `@kit/ui`, and a workspace shows its own name until someone sets a display name. */
final class KitUiTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    public function test_kit_pages_import_ui_only_from_the_kit_alias(): void
    {
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__).'/docs/generation-manifest.json'), true);
        $checked = 0;
        foreach ($manifest['kit_generated']['paths'] as $path) {
            if (! str_starts_with($path, 'resources/js/pages/') || str_ends_with($path, '/Auth/Login/')) {
                continue;
            }
            $files = is_dir(dirname(__DIR__).'/'.$path) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/'.$path, FilesystemIterator::SKIP_DOTS)) : [new SplFileInfo(dirname(__DIR__).'/'.$path)];
            foreach ($files as $file) {
                if ($file->getExtension() === 'vue') {
                    $this->assertDoesNotMatchRegularExpression('#from\s*[\'"]@/(components|lib|composables)/#', file_get_contents($file->getPathname()), $file->getPathname().' imports UI outside @kit/ui');
                    $checked++;
                }
            }
        }
        $this->assertGreaterThan(10, $checked);
        // The alias resolves in the build and the typecheck, and defaults to the kit's own set.
        $this->assertStringContainsString("'@kit/ui'", (string) file_get_contents(dirname(__DIR__).'/vite.config.ts'));
        $this->assertStringContainsString('"@kit/ui"', (string) file_get_contents(dirname(__DIR__).'/tsconfig.json'));
        $this->assertFileExists(dirname(__DIR__).'/resources/js/components/Kit/index.ts');
        $this->assertFileExists(dirname(__DIR__).'/resources/js/components/Kit/contract.ts');
    }

    public function test_focus_page_sizes_are_a_static_lookup(): void
    {
        $vue = (string) file_get_contents(dirname(__DIR__).'/resources/js/components/Kit/Layout/FocusPage.vue');
        preg_match('/const maxWidths[^{]*\{(.*?)\};/s', $vue, $lookup);
        preg_match_all("/(\w+): '([^']+)'/", $lookup[1] ?? '', $pairs, PREG_SET_ORDER);
        // Each size maps to its whole class string.
        $this->assertSame(['xs' => 'max-w-2xl', 'sm' => 'max-w-3xl', 'md' => 'max-w-5xl', 'lg' => 'max-w-7xl', 'xl' => 'max-w-[96rem]'], array_column($pairs, 2, 1));
        // Never interpolated: no template literal or concatenation builds a class from the size.
        $code = preg_replace('#/\*.*?\*/#s', '', $vue);
        $this->assertDoesNotMatchRegularExpression('/`[^`]*max-w-[^`]*\$\{|max-w-[\'"]\s*\+|\+\s*[\'"]max-w/', $code);
        $this->assertStringContainsString('data-slot="focus-page"', $vue);
        $this->assertStringContainsString("'mx-auto w-full'", $vue);
        // The shell owns the gutters: the primitive adds no padding of its own.
        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])p[xytblr]?-\d/', substr($vue, strpos($vue, '<template>')));
        $this->assertStringContainsString("export { default as FocusPage } from './Layout/FocusPage.vue';", (string) file_get_contents(dirname(__DIR__).'/resources/js/components/Kit/index.ts'));
    }

    public function test_every_kit_page_uses_focus_page_unless_it_is_a_fluid_data_page(): void
    {
        // Fluid data pages (lists, tables, grids that grow with data) use the full width. Fixed-content pages, forms, settings sections,
        // detail and confirmation pages, empty states and guest pages must sit in a FocusPage. Add a page here only when it is fluid.
        $fluid = [];
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__).'/docs/generation-manifest.json'), true);
        $checked = [];
        foreach ($manifest['kit_generated']['paths'] as $path) {
            if (! str_starts_with($path, 'resources/js/pages/')) {
                continue;
            }
            $files = is_dir(dirname(__DIR__).'/'.$path) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/'.$path, FilesystemIterator::SKIP_DOTS)) : [new SplFileInfo(dirname(__DIR__).'/'.$path)];
            foreach ($files as $file) {
                $relative = substr($file->getPathname(), strlen(dirname(__DIR__)) + 1);
                if ($file->getExtension() === 'vue' && basename($file->getPathname()) === 'Page.vue') {
                    $checked[] = $relative;
                    if (! in_array($relative, $fluid, true)) {
                        $this->assertStringContainsString('<FocusPage', (string) file_get_contents($file->getPathname()), $relative.' must sit in a FocusPage');
                    }
                }
            }
        }
        $this->assertGreaterThanOrEqual(6, count($checked));
        $this->assertContains('resources/js/pages/Workspace/Settings/Edit/Page.vue', $checked);
    }

    public function test_the_saved_appearance_is_applied_before_first_paint(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();
        $script = strpos($html, "localStorage.getItem('appearance')");
        $this->assertNotFalse($script, 'a head script applies the saved appearance');
        $this->assertLessThan(strpos($html, 'rel="icon"'), $script, 'the script runs before the rest of the head, so before first paint');
        $this->assertStringContainsString("classList.toggle('dark'", $html);
        // The toggle is in the header and the drawer through the shell's `actions` slot, and names itself.
        $layout = (string) file_get_contents(dirname(__DIR__).'/resources/js/layouts/AppLayout.vue');
        $this->assertStringContainsString('<template #actions><AppearanceToggle />', $layout);
        $toggle = (string) file_get_contents(dirname(__DIR__).'/resources/js/components/Shell/AppearanceToggle.vue');
        $this->assertStringContainsString('aria-label="Dark mode"', $toggle);
        $this->assertStringContainsString(':aria-pressed=', $toggle);
        $this->assertStringContainsString('<slot name="actions" />', (string) file_get_contents(dirname(__DIR__).'/resources/js/pages/Workspace/Partials/ShellDrawer.vue'));
    }

    public function test_the_settings_display_name_is_the_workspace_name_until_it_is_set(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $password = Str::random(40);
        User::findOrFail(1)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
        // Unset at provisioning: the workspace's own name shows, and it follows a rename.
        $this->getJson('/@alpha/settings/general')->assertOk()->assertJsonPath('display_name', 'Alpha');
        Workspace::findOrFail(1)->update(['name' => 'Alpha Renamed']);
        $settings = $this->getJson('/@alpha/settings/general')->assertOk()->assertJsonPath('display_name', 'Alpha Renamed')->json();
        // Once someone names it, that name is kept.
        $this->putJson('/@alpha/settings/general', ['display_name' => 'Chosen name', 'timezone' => 'UTC', 'expected_version' => $settings['version'], 'intent_key' => 'name-it'])->assertOk();
        $this->getJson('/@alpha/settings/general')->assertOk()->assertJsonPath('display_name', 'Chosen name');
    }
}
