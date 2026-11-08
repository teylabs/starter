<?php

// standards-pattern: gap

namespace App\TypeScript;

use Spatie\TypeScriptTransformer\Data\Location;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNamespace;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptOperator;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

/**
 * The global-namespace writer with every nested namespace exported. Upstream writes `namespace Kit {` inside `declare namespace Tey`; a namespace
 * that also holds an `export type` keeps its plain `namespace` children private, so `Tey.Kit.…` doesn't resolve and Vue's SFC compiler fails on
 * defineProps in the production build.
 */
final class ExportedNamespaceWriter extends GlobalNamespaceWriter
{
    protected function buildNamespace(Location $location): TypeScriptNamespace
    {
        return new TypeScriptNamespace(
            $location->name,
            $location->transformed,
            array_map(fn (Location $child): TypeScriptOperator => TypeScriptOperator::export($this->buildNamespace($child)), $location->children),
        );
    }
}
