<?php

use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\QueryConnectionType;
use Laravel\Nightwatch\Records\Query;

test('nightwatch drops cache, lock and telescope SQL and keeps everything else', function () {
    $callbacks = (fn () => $this->rejectQueryCallbacks)->call(app(Core::class));
    $rejected = fn (string $sql): bool => collect($callbacks)->contains(fn (callable $reject) => $reject(new Query($sql, 'x.php', 1, 1, 'pgsql', QueryConnectionType::Write)));

    foreach ([
        'select * from "cache" where "key" in (?)',
        'insert into "cache_locks" ("key", "owner", "expiration") values (?, ?, ?)',
        'update "cache_locks" set "owner" = ? where "key" = ?',
        'delete from "cache" where "key" = ?',
        'insert into "telescope_entries" ("uuid") values (?)',
    ] as $sql) {
        expect($rejected($sql))->toBeTrue($sql);
    }
    foreach ([
        'select * from "users" where "id" = ?',
        'select "cache_key" from "media"',
        'select * from "cache_entries"',
        'insert into "jobs" ("queue") values (?)',
    ] as $sql) {
        expect($rejected($sql))->toBeFalse($sql);
    }
    expect(config('nightwatch.filtering.ignore_cache_events'))->toBeTrue();
});
