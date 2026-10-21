<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/** What a platform sets once a database is attached (CACHE_STORE, SESSION_DRIVER and QUEUE_CONNECTION as `database`) works on a fresh database, and the cache prefix follows APP_NAME. */
final class CloudDefaultsTest extends TestCase
{
    /** The cache config as a deployment with this environment would read it. */
    private function cacheConfig(array $env): array
    {
        $GLOBALS['cloudDefaultsEnv'] = $env;
        if (! function_exists('CloudDefaultsConfig\\env')) {
            eval('namespace CloudDefaultsConfig; function env($key, $default = null) { return $GLOBALS["cloudDefaultsEnv"][$key] ?? $default; }');
        }
        $source = (string) preg_replace('/^<\?php\s*/', '', (string) file_get_contents(dirname(__DIR__).'/config/cache.php'));

        return eval('namespace CloudDefaultsConfig; '.$source);
    }

    public function test_the_database_cache_store_and_queue_exist_and_the_prefix_follows_the_app_name(): void
    {
        $this->assertSame('database', config('cache.stores.database.driver'));
        $this->assertSame('cache', config('cache.stores.database.table'));
        $this->assertSame('cache_locks', config('cache.stores.database.lock_table'));
        $this->assertSame('database', config('queue.connections.database.driver'));
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('database-uuids', config('queue.failed.driver'));

        $this->assertSame('my-product-cache-', $this->cacheConfig(['APP_NAME' => 'My Product'])['prefix']);
        $this->assertSame('laravel-cache-', $this->cacheConfig([])['prefix'], 'with no name, Laravel\'s own default');
        $this->assertSame('pinned', $this->cacheConfig(['APP_NAME' => 'My Product', 'CACHE_PREFIX' => 'pinned'])['prefix'], 'CACHE_PREFIX pins it');
        $this->assertSame('database', $this->cacheConfig(['CACHE_STORE' => 'database'])['default']);
    }

    public function test_the_database_cache_and_locks_round_trip_on_a_fresh_database(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        config(['cache.default' => 'database']);
        Cache::forgetDriver('database');
        Cache::put('cloud', ['ok' => true], 60);
        $this->assertSame(['ok' => true], Cache::get('cloud'));
        $this->assertSame(1, DB::table('cache')->count());
        $lock = Cache::lock('deploy', 10);
        $this->assertTrue($lock->get());
        $this->assertFalse(Cache::lock('deploy', 10)->get(), 'a held lock is held');
        $lock->release();
        $this->assertTrue(Cache::lock('deploy', 10)->get());
        $this->assertSame(1, DB::table('cache_locks')->count());
    }

    public function test_the_caches_optimize_builds_succeed_with_the_database_store_on_a_fresh_database(): void
    {
        // `optimize` failed on a platform with `relation "cache" does not exist`: laravel-data keeps its structure cache in the cache store.
        require dirname(__DIR__).'/database/fixture.php';
        config(['cache.default' => 'database', 'data.structure_caching.enabled' => true, 'data.structure_caching.cache.store' => 'database']);
        Cache::forgetDriver('database');
        $kernel = app(Kernel::class);
        $this->assertSame(0, $kernel->call('data:cache-structures'));
        $this->assertSame(0, $kernel->call('structure-scouts:cache'));
        $this->assertGreaterThan(0, DB::table('cache')->count(), 'the structures went to the database store');
    }

    public function test_a_database_queue_takes_jobs_into_the_jobs_table(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        config(['queue.default' => 'database']);
        Queue::push(function (): void {});
        $this->assertSame(1, DB::table('jobs')->count());
    }
}
