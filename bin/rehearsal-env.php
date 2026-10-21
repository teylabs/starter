<?php

use Dotenv\Dotenv;

// Builds a throwaway production environment from .env.production.example: every stand-in replaced with a value that is well-formed and worth nothing (a real APP_KEY, real RSA
// keys, a RESEND_KEY that is only a string), so `php artisan kit:preflight --env-file=` can rehearse the deploy with the production-only checks ON, in CI and locally.
//
//   php bin/rehearsal-env.php [--escaped|--base64] [KEY=VALUE ...] > rehearsal.env
//
// `--escaped` writes the PEM keys with literal backslash-n newlines, `--base64` base64-encodes them: the forms a dashboard that cannot hold newlines forces. KEY=VALUE overrides.

require __DIR__.'/../vendor/autoload.php';

$args = array_slice($argv, 1);
$form = in_array('--escaped', $args, true) ? 'escaped' : (in_array('--base64', $args, true) ? 'base64' : 'pem');
$overrides = [];
foreach ($args as $arg) {
    if (str_contains($arg, '=') && ! str_starts_with($arg, '--')) {
        [$key, $value] = explode('=', $arg, 2);
        $overrides[$key] = $value;
    }
}

$pair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($pair, $private);
$public = openssl_pkey_get_details($pair)['key'];
$key = fn (string $pem): string => match ($form) {
    'escaped' => str_replace("\n", '\\n', trim($pem)),
    'base64' => base64_encode($pem),
    default => trim($pem),
};

$values = Dotenv::parse((string) file_get_contents(__DIR__.'/../.env.production.example'));
$values = [
    ...$values,
    'APP_NAME' => 'Rehearsal Product',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_URL' => 'https://rehearsal.example.test',
    'RESEND_KEY' => 're_'.bin2hex(random_bytes(12)),
    'DB_HOST' => '127.0.0.1',
    'DB_DATABASE' => 'rehearsal',
    'DB_USERNAME' => 'rehearsal',
    'DB_PASSWORD' => bin2hex(random_bytes(8)),
    'PASSPORT_PRIVATE_KEY' => $key($private),
    'PASSPORT_PUBLIC_KEY' => $key($public),
    'KIT_BRAND_LOGOMARK' => '/favicon.svg',
    'KIT_BRAND_ACCENT' => '#0f766e',
    'KIT_BRAND_HELLO_EMAIL' => 'hello@rehearsal.test',
    'KIT_BRAND_SUPPORT_EMAIL' => 'support@rehearsal.test',
    'KIT_BRAND_PRIVACY_EMAIL' => 'privacy@rehearsal.test',
    'KIT_BRAND_MAIL_FROM_ADDRESS' => 'updates@rehearsal.test',
    'KIT_BRAND_LEGAL_NAME' => 'Rehearsal Ltd',
    'KIT_BRAND_POSTAL_ADDRESS' => '9 Rehearsal Street, Testville',
    ...$overrides,
];
foreach ($values as $name => $value) {
    echo $name.'="'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], (string) $value).'"'."\n";
}
