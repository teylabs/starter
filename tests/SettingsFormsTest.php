<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

function settingsFormsInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function settingsFormsSignIn(int $id): void
{
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}
test('every page form is an inertia form with form actions or a form card', function () {
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
        // A guest card (sign-in, the invitation) is not a settings form: no sticky FormActions bar, a full-width primary button of its own.
        if (str_contains($name, '/pages/Auth/')) {
            $this->assertStringNotContainsString('<FormActions', $template, $name.' is a guest card: a full-width Button, not the FormActions bar');
            $this->assertStringContainsString('class="w-full"', $template, $name.' has a full-width submit');

            continue;
        }
        // A welcome step is a card inside the FocusShell with its own submit and skip: no FormActions bar either.
        if (str_contains($name, '/pages/Welcome/')) {
            $this->assertStringNotContainsString('<FormActions', $template, $name.' is a card step: its own submit and skip, not the FormActions bar');

            continue;
        }
        if ($count > 0) {
            $forms += $count;
            expect(preg_match_all('/<(FormCard|FormActions)\b/', $template))->toBe($count, $name.' has a <Form> without its FormCard or FormActions');
            $this->assertDoesNotMatchRegularExpression('/<(FormCard|FormActions)[^>]*\sv-if=/', $source, $name.' hides its form actions');
        }
    }
    expect($forms)->toBeGreaterThanOrEqual(4);
});
test('every destructive action goes through the confirm dialog', function () {
    $destructive = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources/js/pages', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'vue') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        $buttons = preg_match_all('/<Button\b[^>]*variant="destructive"/', $source);
        $destructive += $buttons;
        // Each destructive button is the trigger inside a <Dialog> (its own element, so one per button).
        expect(preg_match_all('/<Dialog\b/', $source))->toBeGreaterThanOrEqual($buttons, substr($file->getPathname(), strlen(dirname(__DIR__)) + 1).' has a destructive button outside a ConfirmDialog');
        $this->assertDoesNotMatchRegularExpression('/<Button\b[^>]*(variant="destructive")[^>]*@click="(remove|revoke|transfer)/', $source, 'a destructive click must be a ConfirmDialog confirm');
    }
    expect($destructive)->toBeGreaterThanOrEqual(5);
});
test('every kit input that a form can hold submits a native named value', function () {
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
    expect((string) file_get_contents(dirname(__DIR__).'/package.json'))->toMatch('/"@inertiajs\/vue3": "\^?3\./');
});
test('form actions has the specified surface', function () {
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
    expect(strpos($actions, "if (processing.value) return 'busy'") < strpos($actions, "source.value?.hasErrors) return 'errors'") && strpos($actions, "return 'errors'") < strpos($actions, "return 'dirty'") && strpos($actions, "return 'dirty'") < strpos($actions, "return 'saved'"))->toBeTrue();
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
    expect($found)->toBe(['FormActions.vue'], 'one FormActions, no second bar');
});
test('an owner after the sync can write settings and a viewer cannot', function () {
    require dirname(__DIR__).'/database/fixture.php';
    expect(app(Kernel::class)->call('kit:permissions:sync', ['--no-interaction' => true]))->toBe(0);
    settingsFormsSignIn(1);
    $this->get('/@alpha/settings/general', settingsFormsInertia())->assertOk()->assertJsonPath('props.save.disabled', false)->assertJsonPath('props.save.reason', null)->assertJsonPath('props.save.method', 'put');
    $this->postJson('/logout')->assertNoContent();
    settingsFormsSignIn(3);
    $this->get('/@alpha/settings/general', settingsFormsInertia())->assertOk()->assertJsonPath('props.save', null);
});
test('every registered settings section is an inertia page whose component exists', function () {
    require dirname(__DIR__).'/database/fixture.php';
    foreach (glob(base_path('vendor/laravel/passport/database/migrations/*.php')) as $migration) {
        (require $migration)->up();
    }
    settingsFormsSignIn(1);
    $checked = [];
    foreach (['workspace' => '/@alpha/settings/', 'personal' => '/me/'] as $scope => $prefix) {
        foreach (app(Registry::class)->settingsSections($scope) as $section) {
            $response = $this->get($prefix.$section['key'], settingsFormsInertia());
            if ($response->isRedirect()) {
                continue;
            }
            $response->assertOk();
            expect($response->headers->get('X-Inertia'))->toBe('true', $section['key'].' is not an Inertia response');
            $component = $response->json('component');
            expect(base_path('resources/js/pages/'.$component.'.vue'))->toBeFile($section['key'].' renders '.$component);
            $checked[] = $section['key'];
        }
    }
    expect($checked)->toContain('general');
    expect($checked)->not->toContain('connections');
    expect($checked)->toContain('people');
});
test('button labels are specific verbs never submit ok or save changes', function () {
    $banned = ['Submit', 'OK', 'Okay', 'Save changes', 'Save settings', 'Done', 'Go'];
    $checked = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources/js', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'vue') {
            continue;
        }
        $template = (string) preg_replace('#<script\b.*?</script>#s', '', (string) file_get_contents($file->getPathname()));
        $name = substr($file->getPathname(), strlen(dirname(__DIR__)) + 1);
        // Every label attribute (submit, busy, confirm) and every button's text.
        preg_match_all('/(?:submit-label|busy-label|confirm-label|cancel-label|:submit-label)="([^"]*)"/', $template, $attributes);
        preg_match_all('/<Button\b[^>]*>\s*([^<{]+?)\s*<\/Button>/', $template, $texts);
        foreach ([...$attributes[1], ...$texts[1]] as $label) {
            $checked++;
            expect($banned)->not->toContain(trim($label));
        }
    }
    expect($checked)->toBeGreaterThan(15);

    // The bar's defaults are the specified ones.
    $actions = (string) file_get_contents(dirname(__DIR__).'/resources/js/components/Kit/Form/FormActions.vue');
    $this->assertStringContainsString("submitLabel: 'Save'", $actions);
    $this->assertDoesNotMatchRegularExpression("/submitLabel: '(Submit|OK|Save changes)'/", $actions);
});
test('every settings and account page is the md focus column', function () {
    // Ruled: every settings section and every account page beside the sidebar is <Page md>, the data pages (People) included.
    $pages = array_merge(glob(dirname(__DIR__).'/resources/js/pages/Workspace/Settings/*/Page.vue'), glob(dirname(__DIR__).'/resources/js/pages/Workspace/Account/*/Page.vue'));
    expect(count($pages))->toBeGreaterThanOrEqual(6);
    foreach ($pages as $page) {
        expect((string) file_get_contents($page))->toMatch('/<Page md[\s>]/', basename(dirname($page)).' is <Page md>');
    }
});
test('the cursor rule is the kits stylesheet and disabled items are not pointer events none', function () {
    $root = dirname(__DIR__);
    $kit = (string) file_get_contents($root.'/resources/css/kit.css');
    $this->assertStringContainsString('html:not(.kit-cursor-native)', $kit, 'the cursor rule has its opt-out');
    $this->assertStringContainsString('cursor-pointer', $kit);
    $this->assertStringContainsString('cursor-not-allowed', $kit);
    $app = (string) file_get_contents($root.'/resources/css/app.css');
    $this->assertStringContainsString("@import './kit.css';", $app);
    $this->assertStringNotContainsString('kit-cursor-native', $app, 'the rule lives in the kit stylesheet, not in the app');

    // Disabled menu and select items are blocked by the disabled state, so the not-allowed cursor can show; none of them is pointer-events: none.
    foreach (array_merge(glob($root.'/resources/js/components/ui/dropdown-menu/*.vue'), [$root.'/resources/js/components/Kit/Form/Select.vue']) as $file) {
        $this->assertStringNotContainsString('data-[disabled]:pointer-events-none', (string) file_get_contents($file), basename($file));
        $this->assertStringNotContainsString('cursor-default', (string) file_get_contents($file), basename($file));
    }
});
