<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/** The cache config as a deployment with this environment would read it. */
function cloudDefaultsCacheConfig(array $env): array
{
    $GLOBALS['cloudDefaultsEnv'] = $env;
    if (! function_exists('CloudDefaultsConfig\\env')) {
        eval('namespace CloudDefaultsConfig; function env($key, $default = null) { return $GLOBALS["cloudDefaultsEnv"][$key] ?? $default; }');
    }
    $source = (string) preg_replace('/^<\?php\s*/', '', (string) file_get_contents(dirname(__DIR__).'/config/cache.php'));

    return eval('namespace CloudDefaultsConfig; '.$source);
}
test('the database cache store and queue exist and the prefix follows the app name', function () {
    expect(config('cache.stores.database.driver'))->toBe('database');
    expect(config('cache.stores.database.table'))->toBe('cache');
    expect(config('cache.stores.database.lock_table'))->toBe('cache_locks');
    expect(config('queue.connections.database.driver'))->toBe('database');
    expect(config('queue.default'))->toBe('sync');
    expect(config('queue.failed.driver'))->toBe('database-uuids');

    expect(cloudDefaultsCacheConfig(['APP_NAME' => 'My Product'])['prefix'])->toBe('my-product-cache-');
    expect(cloudDefaultsCacheConfig([])['prefix'])->toBe('laravel-cache-', 'with no name, Laravel\'s own default');
    expect(cloudDefaultsCacheConfig(['APP_NAME' => 'My Product', 'CACHE_PREFIX' => 'pinned'])['prefix'])->toBe('pinned', 'CACHE_PREFIX pins it');
    expect(cloudDefaultsCacheConfig(['CACHE_STORE' => 'database'])['default'])->toBe('database');
});
test('the database cache and locks round trip on a fresh database', function () {
    require dirname(__DIR__).'/database/fixture.php';
    config(['cache.default' => 'database']);
    Cache::forgetDriver('database');
    Cache::put('cloud', ['ok' => true], 60);
    expect(Cache::get('cloud'))->toBe(['ok' => true]);
    expect(DB::table('cache')->count())->toBe(1);
    $lock = Cache::lock('deploy', 10);
    expect($lock->get())->toBeTrue();
    expect(Cache::lock('deploy', 10)->get())->toBeFalse('a held lock is held');
    $lock->release();
    expect(Cache::lock('deploy', 10)->get())->toBeTrue();
    expect(DB::table('cache_locks')->count())->toBe(1);
});
test('the caches optimize builds succeed with the database store on a fresh database', function () {
    // `optimize` failed on a platform with `relation "cache" does not exist`: laravel-data keeps its structure cache in the cache store.
    require dirname(__DIR__).'/database/fixture.php';
    config(['cache.default' => 'database', 'data.structure_caching.enabled' => true, 'data.structure_caching.cache.store' => 'database']);
    Cache::forgetDriver('database');
    $kernel = app(Kernel::class);
    expect($kernel->call('data:cache-structures'))->toBe(0);
    expect($kernel->call('structure-scouts:cache'))->toBe(0);
    expect(DB::table('cache')->count())->toBeGreaterThan(0, 'the structures went to the database store');
});
test('a database queue takes jobs into the jobs table', function () {
    require dirname(__DIR__).'/database/fixture.php';
    config(['queue.default' => 'database']);
    Queue::push(function (): void {});
    expect(DB::table('jobs')->count())->toBe(1);
});
