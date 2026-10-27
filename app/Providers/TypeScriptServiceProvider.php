<?php

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;
use Tey\Kit\TypeScript\ExplanationTypesExtension;
use Tey\Kit\TypeScript\ViewModelTransformer;

final class TypeScriptServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        // The ViewModel transformer goes first: the attributed-class transformer also matches a ViewModel and would quietly emit `{}`.
        $config->transformer(ViewModelTransformer::class)
            ->transformer(AttributedClassTransformer::class)
            ->transformDirectories(base_path('vendor/tey/kit/src/Features'))
            ->outputDirectory(resource_path('js'))
            ->writer(new GlobalNamespaceWriter('types.generated.d.ts'))
            // A view model's ExplanationNode list or ExplanationDocument types as the kit's own type instead of `undefined`.
            ->extension(new ExplanationTypesExtension)
            ->withoutManifest();
    }
}
