<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require dirname(__DIR__).'/vendor/autoload.php';

/** The sign-in page offers Concierge's one-click local sign-in exactly when Concierge says it is available. */
final class LocalLoginOfferTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['concierge.local_login.enabled' => true, 'concierge.local_login.account.email' => 'owner@example.com']);
    }

    public function test_the_offer_shows_and_signs_in_when_concierge_makes_it_available(): void
    {
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        $owner = User::create(['name' => 'Local owner', 'email' => 'owner@example.com', 'email_verified_at' => now()]);
        $environment = getenv('APP_ENV');
        try {
            // A local boot, the way Concierge's provider registers the route for one.
            putenv('APP_ENV=local');
            $this->app['env'] = 'local';
            $this->app->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
            $this->app['router']->getRoutes()->refreshNameLookups();

            $this->get('/login', $this->inertia())->assertOk()->assertJsonPath('component', 'Auth/SignIn/Page')
                ->assertJsonPath('props.localLogin', ['url' => '/local-login', 'email' => 'owner@example.com']);
            $this->withSession(['_token' => 'local-token'])->post('/local-login', ['_token' => 'local-token'])->assertRedirect('/');
            $this->assertAuthenticatedAs($owner);
        } finally {
            putenv('APP_ENV='.$environment);
            $this->app['env'] = $environment;
        }
    }

    public function test_the_offer_is_absent_when_concierge_says_it_is_unavailable(): void
    {
        $this->get('/login', $this->inertia())->assertOk()->assertJsonPath('component', 'Auth/SignIn/Page')
            ->assertJsonPath('props.localLogin', null);
    }

    /** @return array<string, string> */
    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }
}
