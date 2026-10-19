<?php

return ['migrations' => ['table' => 'migrations', 'update_date_on_publish' => true], 'default' => env('DB_CONNECTION', 'sqlite'), 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => env('DB_DATABASE', database_path('database.sqlite')), 'foreign_key_constraints' => true], 'pgsql' => ['driver' => 'pgsql', 'host' => env('DB_HOST', '127.0.0.1'), 'port' => env('DB_PORT', '5432'), 'database' => env('DB_DATABASE'), 'username' => env('DB_USERNAME'), 'password' => env('DB_PASSWORD'), 'charset' => 'utf8', 'prefix' => '', 'schema' => 'public', 'sslmode' => 'prefer']]];
