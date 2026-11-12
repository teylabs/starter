#!/bin/sh
# Runs the starter test suite (Pest) with an app's own brand in config/brand.php (accent, style, ink, logomark, presets, private): the suite must not assume the
# template's brand. The committed config/brand.php is put back afterwards, whatever the suite's result. `composer test:brand-config`.
set -u
cd "$(dirname "$0")/.."
backup="$(mktemp)"
cp config/brand.php "$backup"
trap 'cp "$backup" config/brand.php; rm -f "$backup"' EXIT INT TERM
php bin/ci-brand-config.php --force --bare accent='#0f766e' style=mono ink='#0b1220' logomark=/own-mark.svg \
    presets='{"own":{"accent":"#7c3aed","accent_foreground":"#ffffff","accent_strong":"#6d28d9","accent_dark":"#a78bfa"}}' private=true
vendor/bin/pest "$@"
