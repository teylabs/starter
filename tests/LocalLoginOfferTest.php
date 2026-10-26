<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Tests\Concerns\FreshClone;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Concierge\LocalAuth\LocalLogin;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require_once __DIR__.'/Concerns/FreshClone.php';
beforeEach(function () {
    config(['concierge.local_login.enabled' => true, 'concierge.local_login.account.email' => 'owner@example.com']);
});
test('the offer shows and signs in when concierge makes it available', function () {
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
    $owner = User::create(['name' => 'Local owner', 'email' => 'owner@example.com', 'email_verified_at' => now()]);
    $environment = getenv('APP_ENV');
    try {
        // A local boot, the way Concierge's provider registers the route for one.
        putenv('APP_ENV=local');
        $this->app['env'] = 'local';
        $this->app->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->get('/login', localLoginOfferInertia())->assertOk()->assertJsonPath('component', 'Auth/Login/Page')
            ->assertJsonPath('props.localLogin', ['url' => '/local-login', 'email' => 'owner@example.com']);
        $this->withSession(['_token' => 'local-token'])->post('/local-login', ['_token' => 'local-token'])->assertRedirect('/');
        $this->assertAuthenticatedAs($owner);
    } finally {
        putenv('APP_ENV='.$environment);
        $this->app['env'] = $environment;
    }
});
test('the offer is absent when concierge says it is unavailable', function () {
    $this->get('/login', localLoginOfferInertia())->assertOk()->assertJsonPath('component', 'Auth/Login/Page')
        ->assertJsonPath('props.localLogin', null);
});
test('a fresh clone with no concierge keys offers sign in only when local', function () {
    expect(FreshClone::localLogin('local'))->toBe(['enabled' => true, 'account' => ['email' => 'owner@example.com']]);
    expect(FreshClone::localLogin('production')['enabled'])->toBeFalse();
    expect(FreshClone::localLogin('local', ['CONCIERGE_LOCAL_LOGIN' => 'false'])['enabled'])->toBeFalse();
    expect(FreshClone::localLogin('local', ['CONCIERGE_LOCAL_LOGIN_EMAIL' => 'dev@example.test'])['account']['email'])->toBe('dev@example.test');

    // The key cannot turn it on in production: config says enabled, the runtime check refuses.
    expect(FreshClone::localLogin('production', ['CONCIERGE_LOCAL_LOGIN' => 'true'])['enabled'])->toBeTrue();
    config(['concierge.local_login' => FreshClone::localLogin('production', ['CONCIERGE_LOCAL_LOGIN' => 'true'])]);
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=production');
        $this->app['env'] = 'production';
        expect(app(LocalLogin::class)->isAvailable())->toBeFalse();
        $this->get('/login', localLoginOfferInertia())->assertOk()->assertJsonPath('props.localLogin', null);
    } finally {
        putenv('APP_ENV='.$environment);
        $this->app['env'] = $environment;
    }
});
/** @return array<string, string> */
function localLoginOfferInertia(): array
{
    return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
}
