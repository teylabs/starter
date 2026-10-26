<?php

use App\Models\User;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

test('the app caches only arrays and scalars', function () {
    expect(config('cache.serializable_classes'))->toBeFalse();
    $written = [];
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$written): void {
        $written[$event->key] = $event->value;
    });
    require dirname(__DIR__).'/database/fixture.php';
    $password = Str::random(40);
    User::findOrFail(1)->update(['password' => Hash::make($password)]);
    $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $password])->assertOk();
    $this->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
    $this->get('/me/workspaces')->assertRedirect('/');
    $this->getJson('/@alpha/settings/general')->assertOk();
    $this->putJson('/@alpha/settings/general', ['display_name' => 'Cached Alpha', 'timezone' => 'UTC', 'expected_version' => 1, 'intent_key' => 'cache-guard'])->assertOk();
    $this->getJson('/@alpha/settings/general')->assertOk()->assertJsonPath('display_name', 'Cached Alpha');
    $this->get('/@alpha')->assertOk();
    $this->assertNotSame([], $written, 'The flow writes to the cache');
    foreach ($written as $key => $value) {
        expect(cacheGuardObjects($value))->toBe([], $key.' caches an object');
    }

    // The guard sees an object wherever it is nested.
    expect(cacheGuardObjects(['a' => ['b' => new ArrayObject], 'c' => serialize([new ArrayObject]), 'd' => serialize(['O:1:"x"'])]))->toBe(['a.b', 'c']);
});
/** @return list<string> paths of objects, or of strings holding a serialized object, within the value */
function cacheGuardObjects(mixed $value, string $path = ''): array
{
    if (is_object($value) || (is_string($value) && preg_match('/(^|[;{])[OC]:\d+:"/', $value))) {
        return [$path];
    }
    if (! is_array($value)) {
        return [];
    }
    $found = [];
    foreach ($value as $key => $item) {
        array_push($found, ...cacheGuardObjects($item, ltrim($path.'.'.$key, '.')));
    }

    return $found;
}
