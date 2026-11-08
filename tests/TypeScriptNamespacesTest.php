<?php

// standards-pattern: gap

use Illuminate\Support\Facades\Artisan;

// A bare `php artisan typescript:transform` (not only bin/generate-types.php) exports every nested namespace: a plain `namespace Kit {` inside
// `declare namespace Tey` is private, so `Tey.Kit.…` doesn't resolve in defineProps and the production build fails. Writes to a temp copy only.
test('regenerating the types exports every nested namespace and matches the committed file', function () {
    $path = resource_path('js/types.generated.d.ts');
    $committed = (string) file_get_contents($path);
    try {
        expect(Artisan::call('typescript:transform'))->toBe(0);
        $generated = (string) file_get_contents($path);
    } finally {
        file_put_contents($path, $committed);
    }

    expect($generated)->toMatch('/^declare namespace Tey \{\nexport namespace Kit \{/');
    preg_match_all('/^(?:(export|declare) )?namespace \w+ \{/m', $generated, $namespaces);
    expect(count($namespaces[0]))->toBeGreaterThan(5);
    expect(array_count_values($namespaces[1]))->toBe(['declare' => 1, 'export' => count($namespaces[0]) - 1], 'every namespace below the declared root is exported');
    expect($generated)->toBe($committed, 'resources/js/types.generated.d.ts is stale: run composer types:generate');
});
