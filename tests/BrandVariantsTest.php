<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Kit\Brand;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require dirname(__DIR__).'/vendor/autoload.php';

/** The brand variants: the neutral default, config-driven presets, and a `?brand=` switch that works only in a local environment. Synthetic colours only. */
final class BrandVariantsTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['kit.brand.presets' => [
            'fill-one' => ['accent' => '#2563eb', 'accent_foreground' => '#ffffff', 'accent_strong' => '#1d4ed8', 'accent_dark' => '#60a5fa', 'accent_dark_foreground' => '#09090b'],
            'mono-one' => ['accent' => '#16a34a', 'accent_strong' => '#15803d', 'accent_dark' => '#4ade80', 'style' => 'mono', 'ink' => '#050505'],
        ]]);
    }

    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    private function local(bool $local): void
    {
        putenv('APP_ENV='.($local ? 'local' : 'testing'));
        app()['env'] = $local ? 'local' : 'testing';
    }

    public function test_the_default_is_the_neutral_zinc_with_no_variant_css(): void
    {
        $this->local(true);
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('content="#52525b"', $html);
        $this->assertStringNotContainsString('--brand-marker', $html);
        $this->assertStringNotContainsString('--primary:#', $html);
    }

    public function test_the_switch_chooses_a_preset_for_the_session_and_clears_it_in_a_local_environment(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $password = Str::random(40);
        User::findOrFail(1)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
        // Signed in as the tests do; the environment becomes local for the page requests.
        $this->local(true);
        $this->get('/dashboard?brand=fill-one')->assertOk()->assertSee(':root{--primary:#2563eb;--primary-foreground:#ffffff;--ring:#1d4ed8', false)->assertSee('content="#2563eb"', false);
        // It stays for the session, the environment pill names it, and a second preset replaces it.
        $this->get('/dashboard', $this->inertia())->assertJsonPath('props.layout.env', 'local · preset: fill-one');
        $mono = $this->get('/dashboard?brand=mono-one')->assertOk();
        $mono->assertSee('[data-shell-bar]{--background:#050505', false)->assertDontSee('--primary:#2563eb', false);
        // An unknown name, or none, goes back to the configured brand.
        $this->get('/dashboard?brand=nonsense')->assertOk()->assertDontSee('--brand-marker', false);
        $this->get('/dashboard?brand=fill-one');
        $this->get('/dashboard?brand=')->assertOk()->assertDontSee('--brand-marker', false);
        $this->get('/dashboard', $this->inertia())->assertJsonPath('props.layout.env', 'local');
    }

    public function test_the_switch_is_ignored_outside_a_local_environment(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        foreach (['testing', 'staging', 'production'] as $environment) {
            putenv('APP_ENV='.$environment);
            app()['env'] = $environment;
            $this->get('/login?brand=fill-one')->assertOk()->assertDontSee('--brand-marker', false)->assertDontSee('#2563eb', false);
            $this->assertNull(session('kit.brand_preset'), $environment.' does not keep the preset');
        }
        // Not even a stale session value from a local run takes effect.
        $this->local(false);
        $this->withSession(['kit.brand_preset' => 'fill-one'])->get('/login')->assertDontSee('#2563eb', false);
    }

    public function test_every_configured_preset_passes_the_contrast_guard(): void
    {
        $this->assertSame([], Brand::contrastProblems(Brand::tokens()));
        foreach (array_keys(Brand::presets()) as $name) {
            $this->assertSame([], Brand::contrastProblems(Brand::tokens($name)), $name.' fails 4.5:1');
        }
        $this->assertCount(2, Brand::presets());
    }
}
