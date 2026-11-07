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
        $name = substr($file->getPathname(), strlen(dirname(__DIR__)) + 1);
        // A page tey/kit owns is the kit's to shape (kit:add writes it byte for byte); this reads the app's own pages.
        if (in_array($name, kitOwnedFiles(), true)) {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
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
            // Deletion is off by default (kit.lifecycle): its section stays registered, hidden, and its page is a 404 until the app turns it on.
            if ($section['key'] === 'delete' && ! config('kit.lifecycle.'.($scope === 'workspace' ? 'workspace' : 'account').'_delete')) {
                $response->assertNotFound();

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
    // A LockedForm's read-only trigger is not disabled: its `locked ? '…'` branch may use the default cursor.
    // The kit's own controls are tey/kit's (kit:add byte for byte); the app's shadcn-vue menu is the app's.
    foreach (glob($root.'/resources/js/components/ui/dropdown-menu/*.vue') as $file) {
        $source = (string) preg_replace("/locked \\? '[^']*'/", '', (string) file_get_contents($file));
        $this->assertStringNotContainsString('data-[disabled]:pointer-events-none', $source, basename($file));
        $this->assertStringNotContainsString('cursor-default', $source, basename($file));
    }
});
