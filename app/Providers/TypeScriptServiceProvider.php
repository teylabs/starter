<?php

namespace App\Providers;

use App\TypeScript\ExportedNamespaceWriter;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Tey\Kit\TypeScript\AttributedDataTransformer;
use Tey\Kit\TypeScript\ExplanationTypesExtension;
use Tey\Kit\TypeScript\ViewModelTransformer;

final class TypeScriptServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        // The ViewModel transformer goes first: the attributed-data transformer also matches a ViewModel and would quietly emit `{}`. The kit's
        // AttributedDataTransformer is Spatie's attributed-class transformer with docblock literal unions (`'auto'|'12h'|'24h'`) kept, not `unknown`.
        $config->transformer(ViewModelTransformer::class)
            ->transformer(AttributedDataTransformer::class)
            ->transformDirectories(base_path('vendor/tey/kit/src/Features'))
            ->outputDirectory(resource_path('js'))
            ->writer(new ExportedNamespaceWriter('types.generated.d.ts'))
            // A view model's ExplanationNode list or ExplanationDocument types as the kit's own type instead of `undefined`.
            ->extension(new ExplanationTypesExtension)
            ->withoutManifest();
    }
}
