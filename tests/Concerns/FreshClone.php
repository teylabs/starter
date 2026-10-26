<?php

namespace Tests\Concerns;

/**
 * A fresh clone sets no CONCIERGE_* keys. `localLogin()` evaluates the app's own `config/concierge.php` as that clone would see it: `env()` answers from the given
 * environment only (APP_ENV plus any overrides), never from the developer's .env, so the zero-config behaviour is the one under test.
 */
final class FreshClone
{
    /** @var array<string, string> */
    public static array $env = [];

    /**
     * @param  array<string, string>  $overrides  CONCIERGE_* keys a developer may set
     * @return array<string, mixed> the `local_login` block of the config
     */
    public static function localLogin(string $appEnv, array $overrides = []): array
    {
        self::$env = ['APP_ENV' => $appEnv] + $overrides;
        if (! function_exists('FreshCloneConfig\\env')) {
            eval('namespace FreshCloneConfig; function env($key, $default = null) { $value = \Tests\Concerns\FreshClone::$env[$key] ?? null; return match ($value) { null => $default, "true" => true, "false" => false, default => $value }; }');
        }
        $source = (string) preg_replace('/^<\?php\s*/', '', (string) file_get_contents(dirname(__DIR__, 2).'/config/concierge.php'));

        $config = eval('namespace FreshCloneConfig; '.$source);

        return $config['local_login'];
    }
}
