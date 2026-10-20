<?php

// Sync until needed: jobs run in the request, so no failed-job table is kept. An app that moves to a real queue adds
// the failed_jobs migration (php artisan make:queue-failed-table) and sets failed to database-uuids.
return ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']], 'failed' => ['driver' => 'null']];
