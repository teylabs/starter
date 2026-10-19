<?php

use PHPUnit\Framework\TestCase;

require dirname(__DIR__).'/vendor/autoload.php';

/** The committed Nightwatch config samples requests at most 1 in 10 and keeps every exception, command and scheduled task. */
final class NightwatchConfigTest extends TestCase
{
    public function test_sampling_rates(): void
    {
        foreach ([null, '1.0', '0.05'] as $override) {
            $override === null ? putenv('NIGHTWATCH_REQUEST_SAMPLE_RATE') : putenv('NIGHTWATCH_REQUEST_SAMPLE_RATE='.$override);
            try {
                $sampling = (require dirname(__DIR__).'/config/nightwatch.php')['sampling'];
            } finally {
                putenv('NIGHTWATCH_REQUEST_SAMPLE_RATE');
            }
            $this->assertLessThanOrEqual(0.1, $sampling['requests']);
            $this->assertSame(['commands' => 1.0, 'exceptions' => 1.0, 'scheduled_tasks' => 1.0], array_diff_key($sampling, ['requests' => true]));
        }
        $this->assertSame(0.1, (require dirname(__DIR__).'/config/nightwatch.php')['sampling']['requests']);
    }
}
