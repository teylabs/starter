<?php

use Tey\Kit\Features\Workspaces\Rls;

test('row-level security is on and every workspace table is protected or listed with its reason', function () {
    if (config('database.default') !== 'pgsql') {
        $this->markTestSkipped('Row-level security is PostgreSQL only.');
    }

    expect(Rls::enabled())->toBeTrue();
    expect(Rls::unprotected())->toBe([]);
});
