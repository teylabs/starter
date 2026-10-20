<?php

use PHPUnit\Framework\TestCase;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * Each feature of the tey/kit library (vendor/tey/kit/src/Features/<Feature>) may use only itself and the features it requires.
 * Code under <Feature>/Integrations/<Other> may also use <Other>: that is where optional integrations live, wired through the registry.
 * Each generated page under resources/js/pages/<Resource>/<Action> imports only from its own folder, and no page imports a layout.
 */
final class FeatureGraphTest extends TestCase
{
    /** Direct requirements; the allowed set is their transitive closure. */
    private const REQUIRES = [
        'Core' => [],
        'Workspaces' => ['Core'],
        'Access' => ['Workspaces'],
        'Settings' => ['Workspaces', 'Access'],
        'Mcp' => ['Workspaces', 'Access'],
        'SessionUi' => ['Workspaces', 'Access'],
    ];

    /** Library features use no host class; everything else under Tey\Kit\ belongs to the composition root or the lifecycle. */
    private const PREFIX = 'Tey\\Kit\\';

    public function test_the_graph_is_acyclic_and_names_known_features(): void
    {
        foreach (array_keys(self::REQUIRES) as $feature) {
            $this->assertNotContains($feature, $this->closure($feature, []), $feature.' requires itself');
        }
        $this->assertEqualsCanonicalizing(array_keys(self::REQUIRES), array_map('basename', $this->featureDirectories()));
    }

    public function test_php_features_use_only_required_features(): void
    {
        $violations = [];
        $files = 0;
        foreach ($this->featureDirectories() as $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() === 'php') {
                    $files++;
                    array_push($violations, ...$this->phpViolations($file->getPathname(), file_get_contents($file->getPathname())));
                }
            }
        }
        $this->assertGreaterThan(40, $files);
        $this->assertSame([], $violations);
    }

    public function test_pages_import_only_their_own_folder(): void
    {
        $violations = [];
        $root = dirname(__DIR__).'/resources/js/pages';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (in_array($file->getExtension(), ['vue', 'ts'], true)) {
                array_push($violations, ...$this->jsViolations($root, $file->getPathname(), file_get_contents($file->getPathname())));
            }
        }
        $this->assertSame([], $violations);
    }

    public function test_the_checks_catch_violations(): void
    {
        $app = $this->library();
        $this->assertSame(
            ['SessionUi/Operations/Bad.php uses Tey\\Kit\\Features\\Mcp\\Context', 'SessionUi/Operations/Bad.php uses Tey\\Kit\\Features\\FeaturesServiceProvider', 'SessionUi/Operations/Bad.php uses Tey\\Kit\\Lineages', 'SessionUi/Operations/Bad.php uses App\\Models\\User'],
            $this->phpViolations($app.'/SessionUi/Operations/Bad.php', "<?php\nnamespace Tey\\Kit\\Features\\SessionUi\\Operations;\nuse Tey\\Kit\\Features\\Mcp\\Context;\nuse Tey\\Kit\\Features\\Access\\Access;\nfinal class Bad { public function a() { return [\\Tey\\Kit\\Features\\FeaturesServiceProvider::class, \\Tey\\Kit\\Lineages::class, \\App\\Models\\User::class, Models\\Note::class]; } }"),
        );
        $this->assertSame([], $this->phpViolations($app.'/SessionUi/Integrations/Mcp/Good.php', "<?php\nnamespace Tey\\Kit\\Features\\SessionUi\\Integrations\\Mcp;\nuse Tey\\Kit\\Features\\Mcp\\Context;\nfinal class Good {}"));
        $this->assertSame(['Core/Bad.php uses Tey\\Kit\\Features\\Workspaces\\Models\\Workspace'], $this->phpViolations($app.'/Core/Bad.php', "<?php\nnamespace Tey\\Kit\\Features\\Core;\nuse Tey\\Kit\\Features\\Workspaces\\Models\\Workspace;\nfinal class Bad {}"));
        $js = dirname(__DIR__).'/resources/js/pages';
        $this->assertSame(
            ['Workspace/Settings/General/Page.vue imports ../../Layout.vue', 'Workspace/Settings/General/Page.vue imports ../../Note/Index/Partials/NoteEditor.vue'],
            $this->jsViolations($js, $js.'/Workspace/Settings/General/Page.vue', "<script setup lang=\"ts\">\nimport Layout from '../../Layout.vue';\nimport NoteEditor from '../../Note/Index/Partials/NoteEditor.vue';\nimport Field from './Partials/Field.vue';\n</script>"),
        );
    }

    private function library(): string
    {
        return dirname(__DIR__).'/vendor/tey/kit/src/Features';
    }

    /** @return list<string> */
    private function featureDirectories(): array
    {
        return array_values(array_filter(glob($this->library().'/*'), 'is_dir'));
    }

    /** @return list<string> */
    private function closure(string $feature, array $seen): array
    {
        foreach (self::REQUIRES[$feature] as $required) {
            if (! in_array($required, $seen, true)) {
                $seen[] = $required;
                $seen = $this->closure($required, $seen);
            }
        }

        return $seen;
    }

    /** @return list<string> */
    private function allowed(string $relative): array
    {
        $parts = explode('/', $relative);
        $allowed = [$parts[0], ...$this->closure($parts[0], [])];
        if (($parts[1] ?? null) === 'Integrations' && isset($parts[2], self::REQUIRES[$parts[2]])) {
            array_push($allowed, $parts[2], ...$this->closure($parts[2], []));
        }

        return $allowed;
    }

    /** @return list<string> */
    private function phpViolations(string $path, string $source): array
    {
        $relative = substr($path, strlen($this->library().'/'));
        $allowed = $this->allowed($relative);
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
                if (! $inUse && $token->is(T_NAME_QUALIFIED) && ! str_starts_with($name, self::PREFIX)) {
                    $name = $namespace.'\\'.$name;
                }
                $names[] = $name;
            }
        }
        $violations = [];
        foreach (array_unique($names) as $name) {
            if (! str_starts_with($name, self::PREFIX) && ! str_starts_with($name, 'App\\')) {
                continue;
            }
            $segments = explode('\\', $name);
            $ok = $segments[0] === 'Tey' && $segments[2] === 'Features' && isset($segments[4], self::REQUIRES[$segments[3]]) && in_array($segments[3], $allowed, true);
            if (! $ok) {
                $violations[] = $relative.' uses '.$name;
            }
        }

        return $violations;
    }

    /** @return list<string> */
    private function jsViolations(string $root, string $path, string $source): array
    {
        $relative = substr($path, strlen($root) + 1);
        // A page's folder is the one holding its Page.vue; Partials/ belong to it.
        $folder = preg_replace('#/Partials(/.*)?$#', '', dirname($relative));
        preg_match_all("/(?:import|from)\\s*\\(?\\s*['\"](\\.{1,2}\\/[^'\"]+)['\"]/", $source, $matches);
        $violations = [];
        foreach ($matches[1] as $import) {
            $target = $this->normalise(dirname($relative).'/'.$import);
            if (! str_starts_with($target, $folder.'/') || basename($target) === 'Layout.vue') {
                $violations[] = $relative.' imports '.$import;
            }
        }

        return $violations;
    }

    private function normalise(string $path): string
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
}
