<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';

/** The local seed: the owner owns a workspace, whether the database is fresh or the owner arrived first through the one-click sign-in. */
final class FirstOwnerCommandTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'local';
        config(['concierge.local_login.account.email' => 'owner@example.com']);
        $this->assertSame(0, $this->runCommand('migrate', ['--force' => true]));
    }

    private function runCommand($command, $parameters = []): int
    {
        return app(Kernel::class)->call($command, $parameters + ['--no-interaction' => true]);
    }

    public function test_a_fresh_database_gets_the_owner_and_a_workspace_they_own(): void
    {
        $this->assertSame(0, $this->runCommand('starter:first-owner'));
        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame(1, Workspace::query()->memberOf($owner)->count());
    }

    public function test_an_owner_who_exists_without_a_workspace_gets_one_and_a_rerun_adds_nothing(): void
    {
        $owner = User::create(['name' => 'Local owner', 'email' => 'owner@example.com', 'email_verified_at' => now()]);
        $this->assertSame(0, Workspace::query()->memberOf($owner)->count());
        $this->assertSame(0, $this->runCommand('starter:first-owner'));
        $this->assertSame(1, Workspace::query()->memberOf($owner)->count());
        $this->assertSame(0, $this->runCommand('starter:first-owner'));
        $this->assertSame(1, Workspace::query()->memberOf($owner)->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_it_creates_nothing_outside_the_local_environment(): void
    {
        $this->app['env'] = 'production';
        $this->assertSame(1, $this->runCommand('starter:first-owner'));
        $this->assertSame(0, User::query()->count());
    }
}
