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
        // Nothing on it takes input: neither placeholder page, nor the component, has a form.
        foreach (['pages/Workspace/Placeholder/Page.vue', 'pages/Workspace/Settings/Placeholder/Page.vue', 'components/Kit/Feedback/Placeholder.vue'] as $file) {
            $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/'.$file);
            $this->assertDoesNotMatchRegularExpression('/<(form|Form|FormActions|FormCard|input|Input|button|Button)\b/', $source, $file.' has no form or control');
        }
    }
}
