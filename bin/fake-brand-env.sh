#!/bin/sh
# Runs the starter phpunit with an app's own brand in the environment (accent, style, ink, logomark, presets, private): the suite must not assume the template's brand.
# Used by the prepush check; `composer test:brand-env`.
KIT_BRAND_ACCENT='#0f766e' KIT_BRAND_STYLE=mono KIT_BRAND_INK='#0b1220' KIT_BRAND_LOGOMARK=/own-mark.svg \
KIT_BRAND_PRESETS='{"own":{"accent":"#7c3aed","accent_foreground":"#ffffff","accent_strong":"#6d28d9","accent_dark":"#a78bfa"}}' KIT_BRAND_PRIVATE=true \
    vendor/bin/phpunit --bootstrap vendor/autoload.php tests
