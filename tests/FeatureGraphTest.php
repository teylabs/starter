<?php

/** Direct requirements; the allowed set is their transitive closure. */
const FEATURE_GRAPH_REQUIRES = [
    'Core' => [],
    'Workspaces' => ['Core'],
    'Access' => ['Workspaces'],
    'Settings' => ['Workspaces', 'Access'],
    'Mcp' => ['Workspaces', 'Access'],
    'Shell' => ['Core'],
    'SessionUi' => ['Workspaces', 'Access', 'Shell'],
    'Notes' => ['Workspaces', 'Access', 'SessionUi'],
];
/** Library features use no host class; everything else under Tey\Kit\ belongs to the composition root or the lifecycle. */
const FEATURE_GRAPH_PREFIX = 'Tey\\Kit\\';
/** Kit library code outside Features that any feature may use: a namespace prefix ends in a backslash, anything else names one class. */
const FEATURE_GRAPH_KIT_LIBRARY = ['Tey\\Kit\\Mail\\', 'Tey\\Kit\\Brand'];
test('the graph is acyclic and names known features', function () {
    foreach (array_keys(FEATURE_GRAPH_REQUIRES) as $feature) {
        expect(featureGraphClosure($feature, []))->not->toContain($feature);
    }
    expect(array_map('basename', featureGraphFeatureDirectories()))->toEqualCanonicalizing(array_keys(FEATURE_GRAPH_REQUIRES));
});
test('php features use only required features', function () {
    $violations = [];
    $files = 0;
    foreach (featureGraphFeatureDirectories() as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files++;
                array_push($violations, ...featureGraphPhpViolations($file->getPathname(), file_get_contents($file->getPathname())));
            }
        }
    }
    expect($files)->toBeGreaterThan(40);
    expect($violations)->toBe([]);
});
test('pages import only their own folder', function () {
    $violations = [];
    $root = dirname(__DIR__).'/resources/js/pages';
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (in_array($file->getExtension(), ['vue', 'ts'], true)) {
            array_push($violations, ...featureGraphJsViolations($root, $file->getPathname(), file_get_contents($file->getPathname())));
        }
    }
    expect($violations)->toBe([]);
});
test('the checks catch violations', function () {
    $app = featureGraphLibrary();
    expect(featureGraphPhpViolations($app.'/SessionUi/Operations/Bad.php', "<?php\nnamespace Tey\\Kit\\Features\\SessionUi\\Operations;\nuse Tey\\Kit\\Features\\Mcp\\Context;\nuse Tey\\Kit\\Features\\Access\\Access;\nfinal class Bad { public function a() { return [\\Tey\\Kit\\Features\\FeaturesServiceProvider::class, \\Tey\\Kit\\Lineages::class, \\App\\Models\\User::class, Models\\Note::class]; } }"))->toBe(['SessionUi/Operations/Bad.php uses Tey\\Kit\\Features\\Mcp\\Context', 'SessionUi/Operations/Bad.php uses Tey\\Kit\\Features\\FeaturesServiceProvider', 'SessionUi/Operations/Bad.php uses Tey\\Kit\\Lineages', 'SessionUi/Operations/Bad.php uses App\\Models\\User']);
    expect(featureGraphPhpViolations($app.'/SessionUi/Integrations/Mcp/Good.php', "<?php\nnamespace Tey\\Kit\\Features\\SessionUi\\Integrations\\Mcp;\nuse Tey\\Kit\\Features\\Mcp\\Context;\nfinal class Good {}"))->toBe([]);
    expect(featureGraphPhpViolations($app.'/SessionUi/Notifications/Mail.php', "<?php\nnamespace Tey\\Kit\\Features\\SessionUi\\Notifications;\nuse Tey\\Kit\\Mail\\FluentMailMessage;\nuse Tey\\Kit\\Brand;\nuse Tey\\Kit\\BrandAssets;\nfinal class Mail {}"))->toBe(['SessionUi/Notifications/Mail.php uses Tey\\Kit\\BrandAssets']);
    expect(featureGraphPhpViolations($app.'/Core/Bad.php', "<?php\nnamespace Tey\\Kit\\Features\\Core;\nuse Tey\\Kit\\Features\\Workspaces\\Models\\Workspace;\nfinal class Bad {}"))->toBe(['Core/Bad.php uses Tey\\Kit\\Features\\Workspaces\\Models\\Workspace']);
    $js = dirname(__DIR__).'/resources/js/pages';
    expect(featureGraphJsViolations($js, $js.'/Workspace/Settings/General/Page.vue', "<script setup lang=\"ts\">\nimport Layout from '../../Layout.vue';\nimport NoteEditor from '../../Note/Index/Partials/NoteEditor.vue';\nimport Field from './Partials/Field.vue';\n</script>"))->toBe(['Workspace/Settings/General/Page.vue imports ../../Layout.vue', 'Workspace/Settings/General/Page.vue imports ../../Note/Index/Partials/NoteEditor.vue']);
});
function featureGraphLibrary(): string
{
    return dirname(__DIR__).'/vendor/tey/kit/src/Features';
}
/** @return list<string> */
function featureGraphFeatureDirectories(): array
{
    return array_values(array_filter(glob(featureGraphLibrary().'/*'), 'is_dir'));
}
/** @return list<string> */
function featureGraphClosure(string $feature, array $seen): array
{
    foreach (FEATURE_GRAPH_REQUIRES[$feature] as $required) {
        if (! in_array($required, $seen, true)) {
            $seen[] = $required;
            $seen = featureGraphClosure($required, $seen);
        }
    }

    return $seen;
}
/** @return list<string> */
function featureGraphAllowed(string $relative): array
{
    $parts = explode('/', $relative);
    $allowed = [$parts[0], ...featureGraphClosure($parts[0], [])];
    if (($parts[1] ?? null) === 'Integrations' && isset($parts[2], FEATURE_GRAPH_REQUIRES[$parts[2]])) {
        array_push($allowed, $parts[2], ...featureGraphClosure($parts[2], []));
    }

    return $allowed;
}
/** @return list<string> */
function featureGraphPhpViolations(string $path, string $source): array
{
    $relative = substr($path, strlen(featureGraphLibrary().'/'));
    $allowed = featureGraphAllowed($relative);
    $namespace = '';
    $inUse = false;
    $names = [];
    $tokens = PhpToken::tokenize($source);
    foreach ($tokens as $index => $token) {
        if ($token->is(T_NAMESPACE)) {
            $namespace = trim($tokens[$index + 2]->text ?? '');
        } elseif ($token->is(T_USE)) {
            $inUse = true;
        } elseif ($token->text === ';') {
            $inUse = false;
        } elseif ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && ($tokens[$index - 2]->id ?? null) !== T_NAMESPACE) {
            $name = ltrim($token->text, '\\');
            if (! $inUse && $token->is(T_NAME_QUALIFIED) && ! str_starts_with($name, FEATURE_GRAPH_PREFIX)) {
                $name = $namespace.'\\'.$name;
            }
            $names[] = $name;
        }
    }
    $violations = [];
    foreach (array_unique($names) as $name) {
        if (! str_starts_with($name, FEATURE_GRAPH_PREFIX) && ! str_starts_with($name, 'App\\')) {
            continue;
        }
        if (featureGraphKitLibrary($name)) {
            continue;
        }
        $segments = explode('\\', $name);
        $ok = $segments[0] === 'Tey' && $segments[2] === 'Features' && isset($segments[4], FEATURE_GRAPH_REQUIRES[$segments[3]]) && in_array($segments[3], $allowed, true);
        if (! $ok) {
            $violations[] = $relative.' uses '.$name;
        }
    }

    return $violations;
}
function featureGraphKitLibrary(string $name): bool
{
    foreach (FEATURE_GRAPH_KIT_LIBRARY as $allowed) {
        if (str_ends_with($allowed, '\\') ? str_starts_with($name, $allowed) : $name === $allowed) {
            return true;
        }
    }

    return false;
}
/** @return list<string> */
function featureGraphJsViolations(string $root, string $path, string $source): array
{
    $relative = substr($path, strlen($root) + 1);

    // A page's folder is the one holding its Page.vue; Partials/ belong to it.
    $folder = preg_replace('#/Partials(/.*)?$#', '', dirname($relative));
    preg_match_all("/(?:import|from)\\s*\\(?\\s*['\"](\\.{1,2}\\/[^'\"]+)['\"]/", $source, $matches);
    $violations = [];
    foreach ($matches[1] as $import) {
        $target = featureGraphNormalise(dirname($relative).'/'.$import);
        if (! str_starts_with($target, $folder.'/') || basename($target) === 'Layout.vue') {
            $violations[] = $relative.' imports '.$import;
        }
    }

    return $violations;
}
function featureGraphNormalise(string $path): string
{
    $out = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '..' && $out !== [] && end($out) !== '..') {
            array_pop($out);
        } elseif ($part !== '.' && $part !== '') {
            $out[] = $part;
        }
    }

    return implode('/', $out);
}
