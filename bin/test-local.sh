#!/bin/sh
# The whole suite on a laptop, in parallel: the PHP suite (8 processes, without the nightly group), then the browser suite (4 processes).
# Usage: composer test:local. Ctrl-C stops both suites and every worker they started: the trap signals this script's whole process group.
# CI is the gate; this is for a deliberate full local run. Exit status: non-zero when either suite fails.
set -u
trap 'trap - INT TERM; kill -TERM 0 2>/dev/null; exit 130' INT TERM

cd "$(dirname "$0")/.."
php vendor/bin/pest --no-tia --parallel --processes=8 --exclude-group=nightly
php_status=$?
PEST_BROWSER=1 composer test:browser -- --processes=4
browser_status=$?

[ "$php_status" -eq 0 ] && [ "$browser_status" -eq 0 ]
