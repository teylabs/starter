<?php

// Two PostgreSQL connections (tey/kit docs/rls.md): `pgsql` is the runtime connection the app serves requests on, as a NOBYPASSRLS role that owns no
// table (DB_RUNTIME_*); `pgsql_admin` is the migrator, which owns the tables (DB_*). Migrations, seeders and operators run on the admin connection
// (`--database=pgsql_admin`). Without DB_RUNTIME_* both log in as DB_*, which kit:preflight refuses while KIT_RLS is on. SQLite stays for focused checks,
// where RLS is a no-op.
$pgsql = ['driver' => 'pgsql', 'host' => env('DB_HOST', '127.0.0.1'), 'port' => env('DB_PORT', '5432'), 'database' => env('DB_DATABASE'), 'charset' => 'utf8', 'prefix' => '', 'schema' => 'public', 'sslmode' => env('DB_SSLMODE', 'prefer')];

return [
    'allow_destructive' => (bool) env('ALLOW_DESTRUCTIVE_DB', false),
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
    'default' => env('DB_CONNECTION', 'sqlite'),
    'connections' => [
        'sqlite' => ['driver' => 'sqlite', 'database' => env('DB_DATABASE', database_path('database.sqlite')), 'foreign_key_constraints' => true],
        'pgsql' => $pgsql + ['username' => env('DB_RUNTIME_USERNAME', env('DB_USERNAME')), 'password' => env('DB_RUNTIME_PASSWORD', env('DB_PASSWORD'))],
        'pgsql_admin' => $pgsql + ['username' => env('DB_USERNAME'), 'password' => env('DB_PASSWORD')],
    ],
];
