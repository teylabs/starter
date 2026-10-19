<?php

use Illuminate\Support\Facades\Schema;

// Host operation check for kit:add and kit:update, run after each feature's files are written.
// Read-only and fail closed: anything unproven returns false, and the feature stays pending until
// you fix it and run the printed --resume command. Review it: add the checks your app relies on.
return static function (): bool {
    try {
        // The kit's features build on the host's own schema: run `php artisan migrate` first.
        foreach (['migrations', 'users'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    } catch (Throwable) {
        return false;
    }
};
