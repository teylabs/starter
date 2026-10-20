#!/bin/sh
# Asserts the template's feature files equal `kit:add` of every catalog feature on a clean Laravel app.
# Usage: sh bin/kit-parity.sh <clean-app>, where <clean-app> is a fresh `laravel/laravel` checkout with its
# dependencies installed. It adds the template's locked tey/kit and the packages the features need, runs
# kit:install and kit:add, then compares every path a feature owns in both directions.
set -eu
template=$(cd "$(dirname "$0")/.." && pwd)
app=$(cd "$1" && pwd)
locked() { php -r '$l = json_decode(file_get_contents($argv[1]), true); foreach ($l["packages"] as $p) { if ($p["name"] === $argv[2]) { echo ltrim($p["version"], "v"); exit; } } exit(1);' "$template/composer.lock" "$1"; }

cd "$app"
packages=""
for package in tey/kit tey/concierge inertiajs/inertia-laravel laravel/fortify laravel/mcp laravel/passport laravel/pennant lorisleiva/laravel-actions spatie/laravel-data spatie/laravel-multitenancy spatie/laravel-settings; do
    packages="$packages $package:$(locked "$package")"
done
# shellcheck disable=SC2086
composer require $packages --no-interaction --no-progress
php artisan kit:install
touch database/database.sqlite
php artisan migrate --force --path=database/migrations
features=$(php artisan kit:list --json | php -r 'echo implode(" ", array_column(json_decode(stream_get_contents(STDIN), true), "id"));')
# shellcheck disable=SC2086
plan=$(php artisan kit:add $features --json | php -r 'echo json_decode(stream_get_contents(STDIN), true)["plan"];')
# shellcheck disable=SC2086
php artisan kit:add $features --apply --expected-plan="$plan"

status=0
for owned in $(php artisan kit:list --json | php -r 'foreach (json_decode(stream_get_contents(STDIN), true) as $f) { echo implode("\n", $f["owns"]), "\n"; }'); do
    if ! diff -r "$app/$owned" "$template/$owned"; then
        echo "parity: $owned differs from kit:add" >&2
        status=1
    fi
done
[ "$status" -eq 0 ] && echo "parity: the template's feature files equal kit:add $features"
exit "$status"
