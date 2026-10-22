<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Tey\Kit\Features\Workspaces\Models\Workspace;

beforeEach(function () {
    $this->app['env'] = 'local';
    config(['concierge.local_login.account.email' => 'owner@example.com']);
    expect(firstOwnerCommandRunCommand('migrate', ['--force' => true]))->toBe(0);
});
function firstOwnerCommandRunCommand($command, $parameters = []): int
{
    return app(Kernel::class)->call($command, $parameters + ['--no-interaction' => true]);
}
test('a fresh database gets the owner and a workspace they own', function () {
    expect(firstOwnerCommandRunCommand('starter:first-owner'))->toBe(0);
    $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
    expect(Workspace::query()->memberOf($owner)->count())->toBe(1);
});
test('an owner who exists without a workspace gets one and a rerun adds nothing', function () {
    $owner = User::create(['name' => 'Local owner', 'email' => 'owner@example.com', 'email_verified_at' => now()]);
    expect(Workspace::query()->memberOf($owner)->count())->toBe(0);
    expect(firstOwnerCommandRunCommand('starter:first-owner'))->toBe(0);
    expect(Workspace::query()->memberOf($owner)->count())->toBe(1);
    expect(firstOwnerCommandRunCommand('starter:first-owner'))->toBe(0);
    expect(Workspace::query()->memberOf($owner)->count())->toBe(1);
    expect(User::query()->count())->toBe(1);
});
test('it creates nothing outside the local environment', function () {
    $this->app['env'] = 'production';
    expect(firstOwnerCommandRunCommand('starter:first-owner'))->toBe(1);
    expect(User::query()->count())->toBe(0);
});
