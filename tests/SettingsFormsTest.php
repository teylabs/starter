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

    public function test_every_page_form_is_an_inertia_form_with_form_actions_or_a_form_card(): void
    {
        $forms = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources/js/pages', FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            $name = substr($file->getPathname(), strlen(dirname(__DIR__)) + 1);
            // Pages use Inertia's <Form>, never useForm (the standard page shape: <Form> + Field name + FormActions).
            $this->assertStringNotContainsString('useForm', $source, $name.' uses useForm: use Inertia\'s <Form>');
            // A raw <form> is only a filter (role="search"): any other form is Inertia's <Form> ending in FormCard or FormActions, so it has a submit control that is always shown.
            preg_match_all('/<form\b([^>]*)>/', (string) preg_replace('#<script\b.*?</script>#s', '', $source), $raw);
            foreach ($raw[1] as $attributes) {
                $this->assertStringContainsString('role="search"', $attributes, $name.' has a raw <form>: use <Form> with FormCard or FormActions');
            }
            $template = (string) preg_replace('#<script\b.*?</script>#s', '', $source);
            $count = preg_match_all('/<Form[\s>]/', $template);
            if ($count > 0) {
                $forms += $count;
                $this->assertSame($count, preg_match_all('/<(FormCard|FormActions)\b/', $template), $name.' has a <Form> without its FormCard or FormActions');
                $this->assertDoesNotMatchRegularExpression('/<(FormCard|FormActions)[^>]*\sv-if=/', $source, $name.' hides its form actions');
            }
        }
        $this->assertGreaterThanOrEqual(6, $forms);
    }

    public function test_every_destructive_action_goes_through_the_confirm_dialog(): void
    {
        $destructive = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources/js/pages', FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            $buttons = preg_match_all('/<Button\b[^>]*variant="destructive"/', $source);
            $destructive += $buttons;
            // Each destructive button is the trigger inside a <ConfirmDialog> (its own element, so one per button).
            $this->assertGreaterThanOrEqual($buttons, preg_match_all('/<ConfirmDialog\b/', $source), substr($file->getPathname(), strlen(dirname(__DIR__)) + 1).' has a destructive button outside a ConfirmDialog');
            $this->assertDoesNotMatchRegularExpression('/<Button\b[^>]*(variant="destructive")[^>]*@click="(remove|revoke|transfer)/', $source, 'a destructive click must be a ConfirmDialog confirm');
        }
        $this->assertGreaterThanOrEqual(5, $destructive);
    }

    public function test_every_kit_input_that_a_form_can_hold_submits_a_native_named_value(): void
    {
        $root = dirname(__DIR__).'/resources/js/components/Kit/Form/';
        // The uncontrolled <Form> reads native inputs by name: each Reka-based control passes `name` to Reka (which renders a native named input) or to a native input.
        $this->assertStringContainsString(':name="name"', (string) file_get_contents($root.'Select.vue'));
        $select = (string) file_get_contents($root.'Select.vue');
        // A hidden native input mirrors the value (Reka's own native select is empty until its options have mounted) and reports the change to the form.
        $this->assertStringContainsString('type="hidden" :name="name" :value="model ?? \'\'"', $select);
        $this->assertStringContainsString("new Event('input', { bubbles: true })", $select);
        $this->assertStringContainsString(':name="name"', (string) file_get_contents($root.'TimezoneSelect.vue'));
        $this->assertStringContainsString(':name="name"', (string) file_get_contents($root.'Checkbox.vue'));
        foreach (['Input', 'Textarea', 'PasswordInput', 'FileInput'] as $native) {
            $this->assertStringContainsString('<', (string) file_get_contents($root.$native.'.vue'));
            // Attributes (name, value, required) fall through to the native control: no inheritAttrs lock that drops them.
            $this->assertStringNotContainsString('inheritAttrs: false })\ndefineProps', (string) file_get_contents($root.$native.'.vue'));
        }
        $this->assertStringContainsString('v-bind="$attrs"', (string) file_get_contents($root.'Input.vue'));
        $this->assertStringContainsString('v-bind="$attrs"', (string) file_get_contents($root.'PasswordInput.vue'));
        // The field reads its error from the form context by name, and the page shape's peer dependency is Inertia 3.
        $this->assertStringContainsString('useFormContext', (string) file_get_contents($root.'Field.vue'));
        $this->assertMatchesRegularExpression('/"@inertiajs\/vue3": "\^?3\./', (string) file_get_contents(dirname(__DIR__).'/package.json'));
    }

    public function test_form_actions_has_the_specified_surface(): void
    {
        $actions = (string) file_get_contents(dirname(__DIR__).'/resources/js/components/Kit/Form/FormActions.vue');
        // Props, slots and defaults from the spec.
        foreach (['submitLabel', 'busyLabel', 'savedLabel', 'cancelUrl', 'cancel', 'sticky', 'warnOnLeave'] as $prop) {
            $this->assertStringContainsString($prop, $actions);
        }
        foreach (["submitLabel: 'Save'", "busyLabel: 'Saving…'", "savedLabel: 'Saved'", 'cancel: true', 'sticky: true', 'warnOnLeave: true'] as $default) {
            $this->assertStringContainsString($default, $actions);
        }
        foreach (['name="status"', 'name="actions"', 'name="submit"'] as $slot) {
            $this->assertStringContainsString($slot, $actions);
        }
        // The state order, the neutral colour for unsaved changes, the danger colour for errors.
        $this->assertTrue(strpos($actions, "if (processing.value) return 'busy'") < strpos($actions, "source.value?.hasErrors) return 'errors'") && strpos($actions, "return 'errors'") < strpos($actions, "return 'dirty'") && strpos($actions, "return 'dirty'") < strpos($actions, "return 'saved'"));
        $this->assertStringContainsString("dirty: 'text-muted-foreground'", $actions);
        $this->assertStringContainsString("errors: 'text-destructive'", $actions);
        $this->assertStringNotContainsString("dirty: 'text-destructive'", $actions);
        $this->assertStringContainsString('There are errors in your submission.', $actions);
        // The form comes from the context or the prop, with a dev error when it has neither; busy keeps the width and sets aria-busy.
        $this->assertStringContainsString('useFormContext', $actions);
        $this->assertStringContainsString('console.error', $actions);
        $this->assertStringContainsString('import.meta.env?.DEV', $actions);
        $this->assertStringContainsString(':aria-busy="processing || undefined"', $actions);
        $this->assertStringContainsString('invisible', $actions);
        // Progress, announcements, the sticky sentinel, and the leave warning (router before + beforeunload, removed on unmount).
        $this->assertStringContainsString('role="progressbar"', $actions);
        $this->assertStringContainsString(':aria-valuenow=', $actions);
        $this->assertStringContainsString('role="status"', $actions);
        $this->assertStringContainsString('IntersectionObserver', $actions);
        $this->assertStringContainsString("router.on('before'", $actions);
        $this->assertStringContainsString("addEventListener('beforeunload'", $actions);
        $this->assertStringContainsString("removeEventListener('beforeunload'", $actions);
        $this->assertStringContainsString('offBefore?.()', $actions);
        $this->assertStringNotContainsString('window.confirm', $actions);
        $this->assertStringContainsString('type="submit"', $actions);
        // The old name is gone, and there is one FormActions.
        $this->assertFileDoesNotExist(dirname(__DIR__).'/resources/js/components/Kit/Form/FormSubmitBar.vue');
        $found = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources/js', FilesystemIterator::SKIP_DOTS)) as $file) {
            if (in_array($file->getFilename(), ['FormActions.vue', 'FormSubmitBar.vue'], true)) {
                $found[] = $file->getFilename();
            }
        }
        $this->assertSame(['FormActions.vue'], $found, 'one FormActions, no second bar');
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
        $this->assertContains('people', $checked);
    }
}
