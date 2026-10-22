<?php

test('sampling rates', function () {
    foreach ([null, '1.0', '0.05'] as $override) {
        $override === null ? putenv('NIGHTWATCH_REQUEST_SAMPLE_RATE') : putenv('NIGHTWATCH_REQUEST_SAMPLE_RATE='.$override);
        try {
            $sampling = (require dirname(__DIR__).'/config/nightwatch.php')['sampling'];
        } finally {
            putenv('NIGHTWATCH_REQUEST_SAMPLE_RATE');
        }
        expect($sampling['requests'])->toBeLessThanOrEqual(0.1);
        expect(array_diff_key($sampling, ['requests' => true]))->toBe(['commands' => 1.0, 'exceptions' => 1.0, 'scheduled_tasks' => 1.0]);
    }
    expect((require dirname(__DIR__).'/config/nightwatch.php')['sampling']['requests'])->toBe(0.1);
});
