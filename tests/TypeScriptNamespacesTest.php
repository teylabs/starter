<?php

// standards-pattern: gap

use Illuminate\Support\Facades\Artisan;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;

// A bare `php artisan typescript:transform` (not only bin/generate-types.php) exports every nested namespace: a plain `namespace Kit {` inside
// `declare namespace Tey` is private, so `Tey.Kit.…` doesn't resolve in defineProps and the production build fails. The transformer writes into a
// temporary resource path, so the committed file other tests read is never touched.
test('regenerating the types exports every nested namespace and matches the committed file', function () {
    $committed = (string) file_get_contents(resource_path('js/types.generated.d.ts'));
    $temporary = sys_get_temp_dir().'/starter-types-'.bin2hex(random_bytes(6));
    mkdir($temporary.'/js', 0777, true);
    try {
        // The app's own config (TypeScriptServiceProvider), with only the output directory moved.
        $config = app(TypeScriptTransformerConfig::class);
        app()->instance(TypeScriptTransformerConfig::class, new TypeScriptTransformerConfig(...['outputDirectory' => $temporary.'/js'] + get_object_vars($config)));
        expect(Artisan::call('typescript:transform'))->toBe(0);
        $generated = (string) file_get_contents($temporary.'/js/types.generated.d.ts');
    } finally {
        app()->forgetInstance(TypeScriptTransformerConfig::class);
        @unlink($temporary.'/js/types.generated.d.ts');
        @rmdir($temporary.'/js');
        @rmdir($temporary);
    }
    expect(file_get_contents(resource_path('js/types.generated.d.ts')))->toBe($committed, 'the committed file is untouched');

    expect($generated)->toMatch('/^declare namespace Tey \{\nexport namespace Kit \{/');
    preg_match_all('/^(?:(export|declare) )?namespace \w+ \{/m', $generated, $namespaces);
    expect(count($namespaces[0]))->toBeGreaterThan(5);
    expect(array_count_values($namespaces[1]))->toBe(['declare' => 1, 'export' => count($namespaces[0]) - 1], 'every namespace below the declared root is exported');
    expect($generated)->toBe($committed, 'resources/js/types.generated.d.ts is stale: run composer types:generate');
});
