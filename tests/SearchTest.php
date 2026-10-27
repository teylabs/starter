<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\UserState;

test('session search', function () {
    require dirname(__DIR__).'/database/fixture.php';
    $inertia = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    $password = Str::random(40);
    $signIn = function (int $id) use ($password): void {
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        UserState::set($id, 'onboarding.done');
        Auth::forgetGuards();
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    };

    // The owner finds this workspace's people, with a link to each in People; Beta, in another workspace, never appears.
    $signIn(1);
    $page = $this->get('/@alpha/search?q=a', $inertia)->assertOk()->assertJsonPath('component', 'Workspace/Search/Page')->assertJsonPath('props.partial', false);
    expect(array_column($page->json('props.groups'), 'key'))->toBe(['kit.people'])
        ->and(array_column($page->json('props.groups.0.results'), 'label'))->toBe(['Alpha', 'Viewer'])
        ->and($page->json('props.groups.0.results.0.url'))->toContain('settings/people?filter');
    expect($page->getContent())->not->toContain('Beta');
    $page->assertJsonPath('props.shell.search.label', 'Search');
    $this->getJson('/@alpha/search?q=view')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('groups.0.results.0.label', 'Viewer');
    $this->getJson('/@alpha/search?q='.str_repeat('x', 101))->assertUnprocessable();

    // A viewer sees who matched without a People link, and cannot search another workspace.
    $this->postJson('/logout')->assertNoContent();
    $signIn(3);
    $this->get('/@alpha/search?q=alpha', $inertia)->assertOk()->assertJsonPath('props.groups.0.results.0.url', null);
    $this->get('/@beta/search?q=a', $inertia)->assertNotFound();

    // Past the per-minute limit the next search is a 429.
    config(['kit.search.per_minute' => 1]);
    RateLimiter::clear('kit-search:3');
    $this->getJson('/@alpha/search?q=a')->assertOk();
    $this->getJson('/@alpha/search?q=a')->assertStatus(429);

    // With the search capability off, the page is absent and the shell offers no search.
    config(['kit.capabilities' => ['workspace.dashboard' => true, 'workspace.settings' => true, 'workspace.search' => false]]);
    $this->get('/@alpha/search?q=a', $inertia)->assertNotFound();
    $this->get('/@alpha', $inertia)->assertOk()->assertJsonPath('props.shell.search', null);
});
