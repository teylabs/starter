<?php

namespace Tests\Concerns;

/**
 * Brand-dependent tests assume the unconfigured template, whatever brand the developer's .env sets (KIT_BRAND_ACCENT, STYLE, INK, LOGOMARK, PRESETS, PRIVATE, ...).
 * `defaultBrand()` puts the ENTIRE `kit.brand` config back to the package's own defaults: it reads the kit's `config/kit.php` and evaluates it with `env()` ignored
 * (every `env('KEY', $default)` is its default), so a key added to the kit later is covered without listing it here. Call it in `setUp()` after `parent::setUp()`.
 */
trait DefaultBrand
{
    /** The app's own brand, as configured before the reset: tests that depend on whether the app is branded read this, never the reset config. */
    protected array $appBrand = [];

    protected function defaultBrand(): void
    {
        $this->appBrand = (array) config('kit.brand');
        config(['kit.brand' => self::packageBrandDefaults()]);
    }

    /** @return array<string, mixed> */
    protected static function packageBrandDefaults(): array
    {
        static $defaults = null;
        if ($defaults === null) {
            // An unqualified `env()` in a namespace resolves to this namespace's function first: the default, never the environment.
            $source = (string) file_get_contents(dirname(__DIR__, 2).'/vendor/tey/kit/config/kit.php');
            $source = preg_replace('/^<\?php\s*/', '', $source);
            if (! function_exists('KitDefaultBrand\\env')) {
                eval('namespace KitDefaultBrand; function env($key, $default = null) { return $default; }');
            }
            $config = eval('namespace KitDefaultBrand; '.$source);
            $defaults = $config['brand'];
        }

        return $defaults;
    }
}
