<?php

require_once __DIR__.'/../Concerns/FreshClone.php';

use Illuminate\Contracts\Console\Kernel;
use Tests\Concerns\FreshClone;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;

// assertAccessible's scan sees the page Inertia rendered, not the empty root a full load leaves until the page component has loaded: scanned right
// after each navigation, pages whose layout has a <main> and an <h1> never report landmark-one-main or page-has-heading-one.
it('scans the rendered page right after a full load', function (): void {
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        config(['concierge.local_login' => FreshClone::localLogin('local')]);
        app()->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
        app('router')->getRoutes()->refreshNameLookups();
        expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
        expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);

        $page = visit('/login')->click('[data-local-login] button')->assertPathIs('/');
        // axeScan is assertAccessible's scan; the verdict on the pages' other findings is not this test's.
        $found = [];
        for ($round = 0; $round < 4; $round++) {
            foreach (['/', '/me/profile'] as $path) {
                $page->navigate($path);
                foreach (axeScan($page)['violations'] as $violation) {
                    if (in_array($violation['id'], ['landmark-one-main', 'page-has-heading-one'], true)) {
                        $found[] = $violation['id'].' @ '.$path;
                    }
                }
            }
        }
        expect($found)->toBe([]);
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
