<?php

namespace App\Support;

use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

final class TypeScriptProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config->transformer(AttributedClassTransformer::class)
            ->transformDirectories(base_path('vendor/tey/kit/src/Features'))
            ->outputDirectory(resource_path('js'))
            ->writer(new GlobalNamespaceWriter('types.generated.d.ts'))
            ->withoutManifest();
    }
}
