<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tey\Kit\Features\Workspaces\Rls;

test('row-level security is on and every workspace table is protected or listed with its reason', function () {
    if (config('database.default') !== 'pgsql') {
        $this->markTestSkipped('Row-level security is PostgreSQL only.');
    }

    expect(Rls::enabled())->toBeTrue();
    expect(Rls::unprotected())->toBe([]);
});

test('the runtime role sees only the current workspace\'s rows and cannot write into another workspace', function () {
    if (config('database.default') !== 'pgsql') {
        $this->markTestSkipped('Row-level security is PostgreSQL only.');
    }
    $owner = DB::connection();
    if (! $owner->selectOne('select rolsuper or rolcreaterole as can from pg_roles where rolname = current_user')->can) {
        $this->markTestSkipped('The test database user cannot create roles (CI\'s can).');
    }
    $role = 'rls_probe_'.bin2hex(random_bytes(4));
    $owner->statement('create table rls_probe (id bigserial primary key, workspace_id bigint not null, label text not null)');
    Rls::protect('rls_probe');
    // FORCE applies the policy to the table's owner too, so even the seed rows go in inside their own workspace.
    Rls::within(1, fn () => $owner->table('rls_probe')->insert(['workspace_id' => 1, 'label' => 'one']));
    Rls::within(2, fn () => $owner->table('rls_probe')->insert(['workspace_id' => 2, 'label' => 'two']));
    Rls::grantRuntimeRole($role, 'probe-password', $owner);
    config(['database.connections.rls_runtime' => ['username' => $role, 'password' => 'probe-password'] + config('database.connections.pgsql'), 'kit.tenancy.rls.connection' => 'rls_runtime']);
    $runtime = DB::connection('rls_runtime');

    try {
        // The owner the migrations run as is not the role requests run as.
        expect($runtime->selectOne('select current_user as name')->name)->toBe($role);
        expect($runtime->table('rls_probe')->pluck('label')->all())->toBe([], 'outside a workspace nothing is visible');
        expect(Rls::within(1, fn () => $runtime->table('rls_probe')->pluck('label')->all()))->toBe(['one']);
        expect(Rls::within(2, fn () => $runtime->table('rls_probe')->pluck('label')->all()))->toBe(['two']);
        expect(fn () => Rls::within(1, fn () => $runtime->table('rls_probe')->insert(['workspace_id' => 2, 'label' => 'smuggled'])))
            ->toThrow(QueryException::class);
        expect(Rls::within(2, fn () => $runtime->table('rls_probe')->pluck('label')->all()))->toBe(['two'], 'the refused row was not written');
    } finally {
        DB::purge('rls_runtime');
        $owner->statement('drop table rls_probe');
        foreach (['revoke all on all tables in schema public from %s', 'revoke all on all sequences in schema public from %s', 'alter default privileges in schema public revoke all on tables from %s', 'alter default privileges in schema public revoke all on sequences from %s', 'revoke usage on schema public from %s', 'drop role %s'] as $sql) {
            $owner->statement(sprintf($sql, $role));
        }
    }
});
