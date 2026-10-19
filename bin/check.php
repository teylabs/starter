<?php

$allowed = ['FoundationCheck', 'IntentCheck', 'OwnershipCheck', 'BoundaryCheck', 'ConciergeCheck'];
$name = $argv[1] ?? 'FoundationCheck';
if (! in_array($name, $allowed, true)) {
    fwrite(STDERR, "Unknown focused check\n");
    exit(2);
}
try {
    require dirname(__DIR__).'/tests/'.$name.'.php';
} catch (Throwable $exception) {
    fwrite(STDERR, (string) $exception."\n");
    exit(1);
}
