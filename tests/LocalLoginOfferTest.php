<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Tests\Concerns\FreshClone;
use Tests\TestCase;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Concierge\LocalAuth\LocalLogin;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';
require_once __DIR__.'/Concerns/FreshClone.php';

/** The sign-in page offers Concierge's one-click local sign-in exactly when Concierge says it is available. */
final class LocalLoginOfferTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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

            $this->get('/login', $this->inertia())->assertOk()->assertJsonPath('component', 'Auth/Login/Page')
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
        $this->get('/login', $this->inertia())->assertOk()->assertJsonPath('component', 'Auth/Login/Page')
            ->assertJsonPath('props.localLogin', null);
    }

    public function test_a_fresh_clone_with_no_concierge_keys_offers_sign_in_only_when_local(): void
    {
        $this->assertSame(['enabled' => true, 'account' => ['email' => 'owner@example.com']], FreshClone::localLogin('local'));
        $this->assertFalse(FreshClone::localLogin('production')['enabled']);
        $this->assertFalse(FreshClone::localLogin('local', ['CONCIERGE_LOCAL_LOGIN' => 'false'])['enabled']);
        $this->assertSame('dev@example.test', FreshClone::localLogin('local', ['CONCIERGE_LOCAL_LOGIN_EMAIL' => 'dev@example.test'])['account']['email']);
        // The key cannot turn it on in production: config says enabled, the runtime check refuses.
        $this->assertTrue(FreshClone::localLogin('production', ['CONCIERGE_LOCAL_LOGIN' => 'true'])['enabled']);
        config(['concierge.local_login' => FreshClone::localLogin('production', ['CONCIERGE_LOCAL_LOGIN' => 'true'])]);
        $environment = getenv('APP_ENV');
        try {
            putenv('APP_ENV=production');
            $this->app['env'] = 'production';
            $this->assertFalse(app(LocalLogin::class)->isAvailable());
            $this->get('/login', $this->inertia())->assertOk()->assertJsonPath('props.localLogin', null);
        } finally {
            putenv('APP_ENV='.$environment);
            $this->app['env'] = $environment;
        }
    }

    /** @return array<string, string> */
    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }
}
