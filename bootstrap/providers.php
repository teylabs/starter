<?php

use App\Providers\KitHostServiceProvider;
use App\Support\TypeScriptProvider;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;

// The TypeScript transformer is a dev dependency, so production installs (composer install --no-dev) skip its provider.
return [
    ...(class_exists(TypeScriptTransformerApplicationServiceProvider::class) ? [TypeScriptProvider::class] : []),
    KitHostServiceProvider::class,
];
