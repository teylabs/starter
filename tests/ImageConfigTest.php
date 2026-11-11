<?php

/** The images config as a deployment with this environment would read it. */
function imageConfigFor(array $env): array
{
    $GLOBALS['imageConfigEnv'] = $env;
    if (! function_exists('ImageConfig\\env')) {
        eval('namespace ImageConfig; function env($key, $default = null) { return $GLOBALS["imageConfigEnv"][$key] ?? $default; }');
    }
    $source = (string) preg_replace('/^<\?php\s*/', '', (string) file_get_contents(dirname(__DIR__).'/config/images.php'));

    return eval('namespace ImageConfig; '.$source);
}

test('the images config loads with imagick as the default driver', function () {
    expect(config('images.default'))->toBe('imagick');
    expect(imageConfigFor([]))->toBe(['default' => 'imagick']);
    expect(imageConfigFor(['IMAGE_DRIVER' => 'gd'])['default'])->toBe('gd', 'IMAGE_DRIVER picks the driver');
});

test('the configured image driver has its PHP extension loaded', function () {
    $driver = config('images.default');

    expect(extension_loaded($driver))->toBeTrue("IMAGE_DRIVER={$driver} needs ext-{$driver}; install it or set IMAGE_DRIVER to a loaded driver");
});
