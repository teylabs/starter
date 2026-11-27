<?php

use Illuminate\Console\Scheduling\Schedule;

test('the documented worker can never hand a running job to a second worker, and failed jobs are pruned', function () {
    $guide = (string) file_get_contents(dirname(__DIR__).'/docs/deploy.md');
    expect(preg_match('/queue:work <connection>[^`]*--timeout=(\d+)/', $guide, $timeout))->toBe(1, 'docs/deploy.md names the worker command');
    expect(preg_match('/`DB_QUEUE_RETRY_AFTER` \| (\d+)/', $guide, $documented))->toBe(1);
    $retryAfter = (int) config('queue.connections.database.retry_after');

    expect((int) $timeout[1])->toBeLessThan(90, 'a Flex worker stops jobs at 90 seconds');
    expect($retryAfter)->toBe((int) $documented[1], 'the guide and config/queue.php agree');
    expect($retryAfter)->toBeGreaterThan((int) $timeout[1]);

    $commands = collect(app(Schedule::class)->events())->map(fn ($event) => (string) $event->command)->implode("\n");
    expect($commands)->toContain('queue:prune-failed')->toContain('queue:prune-batches');
});
