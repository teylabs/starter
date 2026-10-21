<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/** The app runs and stores in UTC; resources/js/lib/time.ts shows instants in the viewer's zone. */
final class TimezoneTest extends TestCase
{
    public function test_the_app_runs_and_stores_in_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        $this->travelTo('2026-07-01T12:00:00-04:00');
        $user = User::create(['name' => 'Zone', 'email' => 'zone@example.test']);
        $this->assertSame('2026-07-01 16:00:00', DB::table('users')->where('id', $user->id)->value('created_at'));
        $this->assertSame('2026-07-01T16:00:00.000000Z', $user->fresh()->toArray()['created_at']);
    }
}
