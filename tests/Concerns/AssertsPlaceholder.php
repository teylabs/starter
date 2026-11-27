<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Route;

/** The placeholder contract, as one assertion: a member gets the page, it answers GET and HEAD only, and nothing on it takes input. */
trait AssertsPlaceholder
{
    /**
     * @param  string  $route  the real page's route name
     * @param  array<string, string>  $headers  the Inertia headers of the signed-in member
     */
    protected function assertPlaceholder(string $route, string $path, array $headers): void
    {
        $this->get($path, $headers)->assertOk()->assertJsonPath('component', fn (string $component): bool => str_ends_with($component, 'Placeholder/Page'));
        $methods = array_diff(Route::getRoutes()->getByName($route)?->methods() ?? ['?'], ['GET', 'HEAD']);
        $this->assertSame([], array_values($methods), $route.' answers GET and HEAD only');
        foreach (['post', 'put', 'patch', 'delete'] as $verb) {
            $this->{$verb}($path)->assertStatus(405);
        }
        // Nothing on it takes input: neither placeholder page, nor the component, has a form. The component's only Buttons are links (:href), as in kit 0.51.
        foreach (['resources/js/pages/Workspace/Placeholder/Page.vue', 'resources/js/pages/Workspace/Settings/Placeholder/Page.vue', 'vendor/tey/kit/resources/js/components/Kit/Feedback/Placeholder.vue'] as $file) {
            $source = (string) file_get_contents(dirname(__DIR__, 2).'/'.$file);
            $this->assertDoesNotMatchRegularExpression('/<(form|Form|FormActions|FormCard|input|Input|button)\b/', $source, $file.' has no form or control');
            preg_match_all('/<Button\b[^>]*>/', $source, $buttons);
            foreach ($buttons[0] as $button) {
                $this->assertStringContainsString(':href=', $button, $file.' has a Button that is not a link');
            }
        }
    }
}
