<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\DefaultBrand;
use Tey\Kit\Brand;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require_once __DIR__.'/Concerns/DefaultBrand.php';
uses(DefaultBrand::class);

beforeEach(function () {
    $this->defaultBrand();
    config(['kit.brand.presets' => [
        'fill-one' => ['accent' => '#2563eb', 'accent_foreground' => '#ffffff', 'accent_strong' => '#1d4ed8', 'accent_dark' => '#60a5fa', 'accent_dark_foreground' => '#09090b'],
        'mono-one' => ['accent' => '#16a34a', 'accent_strong' => '#15803d', 'accent_dark' => '#4ade80', 'style' => 'mono', 'ink' => '#050505'],
    ]]);
});
function brandVariantsInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
function brandVariantsLocal(bool $local): void
{
    putenv('APP_ENV='.($local ? 'local' : 'testing'));
    app()['env'] = $local ? 'local' : 'testing';
}
test('the default is the neutral zinc with no variant css', function () {
    brandVariantsLocal(true);
    $html = $this->get('/login')->assertOk()->getContent();
    $this->assertStringContainsString('content="#52525b"', $html);
    $this->assertStringNotContainsString('--brand-marker', $html);
    $this->assertStringNotContainsString('--primary:#', $html);
});
test('the switch chooses a preset for the session and clears it in a local environment', function () {
    require dirname(__DIR__).'/database/fixture.php';
    $password = Str::random(40);
    User::findOrFail(1)->update(['password' => Hash::make($password)]);
    $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $password])->assertOk();
    $this->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();

    // Signed in as the tests do; the environment becomes local for the page requests.
    brandVariantsLocal(true);
    $this->get('/?brand=fill-one')->assertOk()->assertSee(':root{--primary:#2563eb;--primary-foreground:#ffffff;--ring:#1d4ed8', false)->assertSee('content="#2563eb"', false);

    // It stays for the session, and a second preset replaces it.
    $this->get('/', brandVariantsInertia())->assertOk();
    expect(session('kit.brand_preset'))->toBe('fill-one');
    $mono = $this->get('/?brand=mono-one')->assertOk();
    $mono->assertSee('[data-shell-bar]{--background:#050505', false)->assertDontSee('--primary:#2563eb', false);

    // An unknown name, or none, goes back to the configured brand.
    $this->get('/?brand=nonsense')->assertOk()->assertDontSee('--brand-marker', false);
    $this->get('/?brand=fill-one');
    $this->get('/?brand=')->assertOk()->assertDontSee('--brand-marker', false);
    $this->get('/', brandVariantsInertia())->assertOk();
    expect(session('kit.brand_preset'))->toBeNull();
});
test('the switch is ignored outside a local environment', function () {
    require dirname(__DIR__).'/database/fixture.php';
    foreach (['testing', 'staging', 'production'] as $environment) {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
        $this->get('/login?brand=fill-one')->assertOk()->assertDontSee('--brand-marker', false)->assertDontSee('#2563eb', false);
        expect(session('kit.brand_preset'))->toBeNull($environment.' does not keep the preset');
    }

    // Not even a stale session value from a local run takes effect.
    brandVariantsLocal(false);
    $this->withSession(['kit.brand_preset' => 'fill-one'])->get('/login')->assertDontSee('#2563eb', false);
});
test('every configured preset passes the contrast guard', function () {
    expect(Brand::contrastProblems(Brand::tokens()))->toBe([]);
    foreach (array_keys(Brand::presets()) as $name) {
        expect(Brand::contrastProblems(Brand::tokens($name)))->toBe([], $name.' fails 4.5:1');
    }
    expect(Brand::presets())->toHaveCount(2);
});
