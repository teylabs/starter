<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

test('the app runs and stores in utc', function () {
    expect(config('app.timezone'))->toBe('UTC');
    expect(date_default_timezone_get())->toBe('UTC');
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
    $this->travelTo('2026-07-01T12:00:00-04:00');
    $user = User::create(['name' => 'Zone', 'email' => 'zone@example.test']);
    expect(DB::table('users')->where('id', $user->id)->value('created_at'))->toBe('2026-07-01 16:00:00');
    expect($user->fresh()->toArray()['created_at'])->toBe('2026-07-01T16:00:00.000000Z');
});
