<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/** Kit pages render UI only through `@kit/ui`, and a workspace shows its own name until someone sets a display name. */
final class KitUiTest extends TestCase
{
    public function test_kit_pages_import_ui_only_from_the_kit_alias(): void
    {
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__).'/docs/generation-manifest.json'), true);
        $checked = 0;
        foreach ($manifest['kit_generated']['paths'] as $path) {
            if (! str_starts_with($path, 'resources/js/pages/') || str_ends_with($path, '/Auth/Login/Page.vue')) {
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

    public function test_page_widths_are_a_static_lookup(): void
    {
        $vue = (string) file_get_contents(dirname(__DIR__).'/resources/js/components/Kit/Layout/FocusPage.vue');
        preg_match('/const maxWidths[^{]*\{(.*?)\};/s', $vue, $lookup);
        preg_match_all("/(\w+): '([^']+)'/", $lookup[1] ?? '', $pairs, PREG_SET_ORDER);
        // Each size maps to its whole class string.
        $this->assertSame(['xs' => 'max-w-2xl', 'sm' => 'max-w-3xl', 'md' => 'max-w-5xl', 'lg' => 'max-w-7xl', 'xl' => 'max-w-[96rem]', 'full' => 'max-w-none'], array_column($pairs, 2, 1));
        // Never interpolated: no template literal or concatenation builds a class from the size.
        $code = preg_replace('#/\*.*?\*/#s', '', $vue);
        $this->assertDoesNotMatchRegularExpression('/`[^`]*max-w-[^`]*\$\{|max-w-[\'"]\s*\+|\+\s*[\'"]max-w/', $code);
        $this->assertStringContainsString('data-slot="focus-page"', $vue);
        $this->assertStringContainsString("'mx-auto w-full'", $vue);
        // The shell owns the gutters: the primitive adds no padding of its own.
        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])p[xytblr]?-\d/', substr($vue, strpos($vue, '<template>')));
        $index = (string) file_get_contents(dirname(__DIR__).'/resources/js/components/Kit/index.ts');
        $this->assertStringContainsString("export { default as Page } from './Layout/Page.vue';", $index);
        // FocusPage is internal to <Page>, never exported.
        $this->assertStringNotContainsString('FocusPage', $index);
        // Widths are boolean props (`<Page sm>`), none is full; the heading carries its own utilities, not a stylesheet rule.
        $page = (string) file_get_contents(dirname(__DIR__).'/resources/js/components/Kit/Layout/Page.vue');
        $this->assertStringContainsString('xs?: boolean; sm?: boolean; md?: boolean; lg?: boolean; xl?: boolean; full?: boolean', $page);
        $this->assertStringContainsString("?? 'full'", $page);
        $this->assertStringContainsString('font-serif', $page);
        $this->assertStringNotContainsString('page-heading', (string) file_get_contents(dirname(__DIR__).'/resources/css/app.css'));
    }

    public function test_every_kit_page_roots_in_page_and_fixed_content_declares_a_width(): void
    {
        // Every page roots in <Page>; the default is full, for lists, tables and grids that grow with data. A page whose content does not
        // grow (forms, settings sections, detail and confirmation pages, empty states, account and guest pages) declares a focused width
        // (`<Page sm>`). Add a page to $full only when it is a full data page.
        $full = ['resources/js/pages/Workspace/Dashboard/Page.vue'];
        $checked = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources/js/pages', FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen(dirname(__DIR__)) + 1);
            if (basename($relative) !== 'Page.vue') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            $this->assertStringNotContainsString('FocusPage', $source, $relative.' uses <Page>, not FocusPage');
            // A server-driven modal page (Invite member, Change role) roots in <Modal>, not <Page>: it is a layer over People, not a page.
            $modalPage = preg_match('#/Settings/People/(Invite|ChangeRole)/Page\.vue$#', $relative) === 1;
            $this->assertMatchesRegularExpression($modalPage ? '#<template>\s*<Modal[\s>]#' : '#<template>\s*(<Head[^>]*/>\s*)?(<main>\s*)?<Page[\s>]#', $source, $relative.' roots in '.($modalPage ? '<Modal>' : '<Page>'));
            if ($modalPage) {
                $checked[] = $relative;

                continue;
            }
            // Every settings section and /me page is the same focus column: <Page md>.
            if (str_contains($relative, '/Workspace/Settings/') || str_contains($relative, '/Workspace/Account/')) {
                $this->assertMatchesRegularExpression('#<Page md[\s>]#', $source, $relative.' is a settings page and must be <Page md>');
            }
            if (! in_array($relative, $full, true)) {
                $this->assertMatchesRegularExpression('#<Page (xs|sm|md|lg|xl)[\s>]#', $source, $relative.' has fixed content and must declare a width');
            }
            $checked[] = $relative;
        }
        $this->assertGreaterThanOrEqual(6, count($checked));
        $this->assertContains('resources/js/pages/Workspace/Settings/General/Page.vue', $checked);
    }

    public function test_hand_rolled_controls_live_only_in_kit_components(): void
    {
        $root = dirname(__DIR__).'/resources/js';
        $outside = [];
        foreach (['pages', 'layouts', 'components/Shell'] as $folder) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder, FilesystemIterator::SKIP_DOTS)) as $file) {
                if (! in_array($file->getExtension(), ['vue', 'ts'], true)) {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                $relative = substr($file->getPathname(), strlen($root) + 1);
                // Pages and the shell use the kit's controls (Input, Checkbox, Button, PasswordInput, the toggles), never raw form elements.
                $this->assertDoesNotMatchRegularExpression('/<(input|button|select|textarea|label)\b/', $source, $relative.' has a raw form element: use the kit component');
                // Pressed state and key handling belong to Reka, inside kit components, not hand-rolled in a page or the shell.
                $this->assertDoesNotMatchRegularExpression('/aria-pressed|@keydown|@keyup|@keypress|onKey(down|up)/', $source, $relative.' hand-rolls pressed state or key handling');
                $outside[] = $relative;
            }
        }
        $this->assertGreaterThan(10, count($outside));
        // The appearance controls and the password input are kit components on Reka, exported from @kit/ui.
        $index = (string) file_get_contents($root.'/components/Kit/index.ts');
        foreach (['AppearanceToggle', 'AppearanceChoice', 'PasswordInput'] as $name) {
            $this->assertStringContainsString('as '.$name.' }', $index);
        }
        $this->assertFileDoesNotExist($root.'/pages/Auth/SignIn/Page.vue');
        $this->assertFileDoesNotExist($root.'/components/Shell/Segmented.vue');
        // <Page> warns in development when more than one width is set.
        $this->assertStringContainsString('set one width at most', (string) file_get_contents($root.'/components/Kit/Layout/Page.vue'));
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
        $toggle = (string) file_get_contents(dirname(__DIR__).'/resources/js/components/Kit/Appearance/AppearanceToggle.vue');
        $this->assertStringContainsString('aria-label="Dark mode"', $toggle);
        // Reka's Toggle holds the pressed state (it renders aria-pressed), so none is hand-rolled here.
        $this->assertStringContainsString('<Toggle', $toggle);
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
