<?php

use App\Providers\KitHostServiceProvider;
use App\Providers\TypeScriptServiceProvider;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;

// The TypeScript transformer is a dev dependency, so production installs (composer install --no-dev) skip its provider.
return [
    ...(class_exists(TypeScriptTransformerApplicationServiceProvider::class) ? [TypeScriptServiceProvider::class] : []),
    KitHostServiceProvider::class,
];
