<?php

// .agents/setup's commit-msg hook block, run in throwaway repositories by tests/scripts/agents-setup-hook-check.php:
// hooksPath containment (nothing created outside the checkout) and the AI-trailer filter. No app boot, no database.
it('installs the trailer hook only inside the checkout and strips only AI attribution', function () {
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/scripts/agents-setup-hook-check.php').' 2>&1', $output, $code);

    expect($code)->toBe(0, implode("\n", $output));
});
