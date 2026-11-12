<?php

// The app's brand, read by tey/kit as `kit.brand` (the product's name is APP_NAME). Same keys and nesting as the kit's stub,
// vendor/tey/kit/resources/stubs/brand.php, which lists every key with its default.
//
// A key set here wins over its KIT_BRAND_* env value; a key left out uses env (deprecated, removed in tey/kit 0.17), then the kit's
// default. Colours are a #rgb or #rrggbb; null means "use the theme"; an empty string is refused.
//
// The template sets none: each line below is the kit's default, the stand-in an app replaces with its own before it deploys
// (php artisan kit:preflight and /up refuse these in production; kit's Brand::placeholders() lists what is left). Uncomment a key to
// set it, commit this file, and keep brand values out of .env. `php artisan kit:brand` then makes the favicons, touch icon, mail mark
// and social image from the logomark and accent.
return [
    // 'logomark' => '/logomark.svg',
    // The default accent is a neutral zinc. A brand may also set its companions (each #rrggbb): accent_foreground (text on the
    // accent), accent_strong (the accent as links and focus on white: 4.5:1), accent_dark (fills in dark mode), and
    // 'style' => 'fill'|'mono' (mono: an ink header and primary buttons, the accent only a highlight).
    // 'accent' => '#52525b',
    // To compare variants side by side in a LOCAL environment, name them here and open any page with ?brand=<name> (?brand= clears
    // it); the switch is ignored everywhere else.
    // 'presets' => ['example' => ['accent' => '#2563eb', 'accent_foreground' => '#ffffff', 'accent_strong' => '#1d4ed8', 'accent_dark' => '#60a5fa']],

    // The four address roles: updates@ is the From of system mail, support@ its Reply-To and the help link, hello@ general contact,
    // privacy@ data requests (left out of a private app).
    // 'hello_email' => 'hello@example.com',
    // 'support_email' => 'support@example.com',
    // 'privacy_email' => 'privacy@example.com',
    // 'mail_from_address' => 'updates@example.com',
    // 'legal_name' => 'Example Company',
    // 'postal_address' => '1 Example Street, Example City',
    // 'terms_url' => '/terms',
    // 'privacy_url' => '/privacy',

    // The footer's credit line: "Built by Tey Labs Inc.". A client build overrides these. 'copyright' => false drops the "© {year}"
    // before the identity.
    // 'credit_verb' => 'Built',
    // 'credit_org' => 'Tey Labs Inc.',
    // 'copyright' => true,
    // 'credit_for' => null,

    // A private app (an internal tool or a client's own workspace) has no public legal entity: with this on, the legal name, postal
    // address, Terms and Privacy are left out of the web and mail footers, and `php artisan kit:brand` does not ask for them. A part
    // left empty is omitted either way.
    // 'private' => false,
];
