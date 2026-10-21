<?php

use Glhd\Gretel\Support\Cache;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

// CI only: asserts what `php artisan migrate --force && php artisan optimize` did on a fresh database, in a production install (no
// dev packages, so no tinker). Exits non-zero with the first thing that is missing.

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fail = function (string $message): never {
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
};

$roles = Role::query()->pluck('name')->sort()->values()->all();
if ($roles !== ['admin', 'owner', 'viewer']) {
    $fail('roles not synced: '.json_encode($roles));
}
if (DB::table('role_has_permissions')->count() === 0) {
    $fail('no default grants');
}
if (DB::table('kit_meta')->where('key', 'catalog_fingerprint')->doesntExist()) {
    $fail('no catalog fingerprint');
}
foreach (['bootstrap/cache/config.php', 'bootstrap/cache/routes-v7.php'] as $cache) {
    if (! is_file(base_path($cache))) {
        $fail('not cached: '.$cache);
    }
}
if (! app(Cache::class)->exists()) {
    $fail('breadcrumbs not cached');
}
$status = app(Illuminate\Contracts\Http\Kernel::class)->handle(Request::create(rtrim((string) config('app.url'), '/').'/up'))->getStatusCode();
if ($status !== 200) {
    $fail('/up answered '.$status);
}
echo 'deploy assertions passed'.PHP_EOL;
