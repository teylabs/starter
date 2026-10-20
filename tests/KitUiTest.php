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
