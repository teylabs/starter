<?php

// QUEUE_CONNECTION picks the connection: `sync` (the default) runs jobs in the request; `database` uses the jobs tables, which are migrations, and records failed jobs in
// `failed_jobs`. A platform that runs workers sets `database`; Laravel Cloud's managed queue injects its own `cloud` connection. retry_after (180s) stays well
// above the worker's --timeout (85s, under a Flex worker's 90s limit), so a job is never handed to a second worker while the first still runs it.
// docs/deploy.md, Queues, has the worker settings.
return [
    'default' => env('QUEUE_CONNECTION', 'sync'),
    'connections' => [
        'sync' => ['driver' => 'sync'],
        'database' => ['driver' => 'database', 'connection' => env('DB_QUEUE_CONNECTION'), 'table' => env('DB_QUEUE_TABLE', 'jobs'), 'queue' => env('DB_QUEUE', 'default'), 'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 180), 'after_commit' => false],
    ],
    'batching' => ['database' => env('DB_CONNECTION', 'sqlite'), 'table' => 'job_batches'],
    'failed' => ['driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'), 'database' => env('DB_CONNECTION', 'sqlite'), 'table' => 'failed_jobs'],
];
