<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require dirname(__DIR__).'/vendor/autoload.php';

/** Every form page has a submit control, a user who may save sees the save, and every settings section is a real Inertia page. */
final class SettingsFormsTest extends TestCase
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
        $password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    }

    public function test_every_page_with_a_form_has_a_submit_control(): void
    {
        $forms = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources/js/pages', FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (! preg_match('/<form\b/', $source)) {
                continue;
            }
            $forms++;
            // The kit's Button submits unless it says `type="button"`; a raw `type="submit"` also counts. It is rendered unconditionally (no v-if on the
            // control or on the row that holds it), so a user who may not save still sees why.
            preg_match_all('/<Button\b([^>]*)>/', $source, $buttons);
            $submits = array_filter($buttons[1], fn (string $attributes): bool => ! preg_match('/\btype="button"/', $attributes));
            $this->assertNotEmpty($submits, substr($file->getPathname(), strlen(dirname(__DIR__)) + 1).' has a <form> and no submit control');
            $this->assertDoesNotMatchRegularExpression('/<FormActions[^>]*\sv-if=/', $source, $file->getFilename().' hides its form actions');
        }
        $this->assertGreaterThanOrEqual(2, $forms);
    }

    public function test_an_owner_after_the_sync_can_write_settings_and_a_viewer_cannot(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(0, app(Kernel::class)->call('kit:permissions:sync', ['--no-interaction' => true]));
        $this->signIn(1);
        $this->get('/@alpha/settings/general', $this->inertia())->assertOk()->assertJsonPath('props.can_write', true);
        $this->postJson('/logout')->assertNoContent();
        $this->signIn(3);
        $this->get('/@alpha/settings/general', $this->inertia())->assertOk()->assertJsonPath('props.can_write', false);
    }

    public function test_every_registered_settings_section_is_an_inertia_page_whose_component_exists(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        foreach (glob(base_path('vendor/laravel/passport/database/migrations/*.php')) as $migration) {
            (require $migration)->up();
        }
        $this->signIn(1);
        $checked = [];
        foreach (['workspace' => '/@alpha/settings/', 'personal' => '/me/'] as $scope => $prefix) {
            foreach (app(Registry::class)->settingsSections($scope) as $section) {
                $response = $this->get($prefix.$section['key'], $this->inertia());
                if ($response->isRedirect()) {
                    continue;
                }
                $response->assertOk();
                $this->assertSame('true', $response->headers->get('X-Inertia'), $section['key'].' is not an Inertia response');
                $component = $response->json('component');
                $this->assertFileExists(base_path('resources/js/pages/'.$component.'.vue'), $section['key'].' renders '.$component);
                $checked[] = $section['key'];
            }
        }
        $this->assertContains('general', $checked);
        $this->assertContains('connections', $checked);
    }
}
