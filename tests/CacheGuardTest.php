<?php

use App\Models\User;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

/** With cache.serializable_classes off, the app caches arrays and scalars only, never objects. */
final class CacheGuardTest extends TestCase
{
    public function createApplication()
    {
        require __DIR__.'/environment.php';

        return require dirname(__DIR__).'/bootstrap/app.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_app_caches_only_arrays_and_scalars(): void
    {
        $this->assertFalse(config('cache.serializable_classes'));
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
        $this->get('/workspaces')->assertOk();
        $this->getJson('/workspaces/1/settings')->assertOk();
        $this->putJson('/workspaces/1/settings', ['display_name' => 'Cached Alpha', 'timezone' => 'UTC', 'expected_version' => 1, 'intent_key' => 'cache-guard'])->assertOk();
        $this->getJson('/workspaces/1/settings')->assertOk()->assertJsonPath('display_name', 'Cached Alpha');
        $this->get('/workspaces/1/notes')->assertOk();
        $this->assertNotSame([], $written, 'The flow writes to the cache');
        foreach ($written as $key => $value) {
            $this->assertSame([], $this->objects($value), $key.' caches an object');
        }

        // The guard sees an object wherever it is nested.
        $this->assertSame(['a.b', 'c'], $this->objects(['a' => ['b' => new ArrayObject], 'c' => serialize([new ArrayObject]), 'd' => serialize(['O:1:"x"'])]));
    }

    /** @return list<string> paths of objects, or of strings holding a serialized object, within the value */
    private function objects(mixed $value, string $path = ''): array
    {
        if (is_object($value) || (is_string($value) && preg_match('/(^|[;{])[OC]:\d+:"/', $value))) {
            return [$path];
        }
        if (! is_array($value)) {
            return [];
        }
        $found = [];
        foreach ($value as $key => $item) {
            array_push($found, ...$this->objects($item, ltrim($path.'.'.$key, '.')));
        }

        return $found;
    }
}
