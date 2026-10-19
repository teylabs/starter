<?php

use PHPUnit\Framework\TestCase;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * Each feature under app/Starter/<Feature> and resources/js/starter/<feature> may use only itself and the features it requires.
 * Code under <Feature>/Integrations/<Other> may also use <Other>: that is where optional integrations live, wired through the registry.
 * A feature's resources/js/starter/<feature> tree is its session UI, so it may also import session-ui.
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
        'Notes' => ['Workspaces', 'Access'],
        'SessionUi' => ['Workspaces', 'Access'],
    ];

    /** Host classes a feature may use; everything else under App\ belongs to the host or the composition root. */
    private const HOST = ['App\\Models\\User'];

    private const JS = ['core' => 'Core', 'workspaces' => 'Workspaces', 'access' => 'Access', 'settings' => 'Settings', 'mcp' => 'Mcp', 'notes' => 'Notes', 'session-ui' => 'SessionUi'];

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

    public function test_js_features_import_only_required_features(): void
    {
        $violations = [];
        $root = dirname(__DIR__).'/resources/js/starter';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (in_array($file->getExtension(), ['vue', 'ts'], true)) {
                array_push($violations, ...$this->jsViolations($root, $file->getPathname(), file_get_contents($file->getPathname())));
            }
        }
        $this->assertSame([], $violations);
    }

    public function test_the_checks_catch_violations(): void
    {
        $app = dirname(__DIR__).'/app/Starter';
        $this->assertSame(
            ['Notes/Operations/Bad.php uses App\\Starter\\Mcp\\Context', 'Notes/Operations/Bad.php uses App\\Starter\\FeatureProvider', 'Notes/Operations/Bad.php uses App\\Support\\StarterLifecycle'],
            $this->phpViolations($app.'/Notes/Operations/Bad.php', "<?php\nnamespace App\\Starter\\Notes\\Operations;\nuse App\\Starter\\Mcp\\Context;\nuse App\\Starter\\Access\\Access;\nuse App\\Models\\User;\nfinal class Bad { public function a() { return [\\App\\Starter\\FeatureProvider::class, \\App\\Support\\StarterLifecycle::class, Models\\Note::class]; } }"),
        );
        $this->assertSame([], $this->phpViolations($app.'/Notes/Integrations/Mcp/Good.php', "<?php\nnamespace App\\Starter\\Notes\\Integrations\\Mcp;\nuse App\\Starter\\Mcp\\Context;\nfinal class Good {}"));
        $this->assertSame(['Core/Bad.php uses App\\Starter\\Workspaces\\Models\\Workspace'], $this->phpViolations($app.'/Core/Bad.php', "<?php\nnamespace App\\Starter\\Core;\nuse App\\Starter\\Workspaces\\Models\\Workspace;\nfinal class Bad {}"));
        $js = dirname(__DIR__).'/resources/js/starter';
        $this->assertSame(['settings/pages/Bad.vue imports ../../notes/components/NoteEditor.vue'], $this->jsViolations($js, $js.'/settings/pages/Bad.vue', "<script setup lang=\"ts\">\nimport WorkspaceLayout from '../../session-ui/layouts/WorkspaceLayout.vue';\nimport NoteEditor from '../../notes/components/NoteEditor.vue';\n</script>"));
    }

    /** @return list<string> */
    private function featureDirectories(): array
    {
        return array_values(array_filter(glob(dirname(__DIR__).'/app/Starter/*'), 'is_dir'));
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
        $relative = substr($path, strlen(dirname(__DIR__).'/app/Starter/'));
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
                if (! $inUse && $token->is(T_NAME_QUALIFIED) && ! str_starts_with($name, 'App\\')) {
                    $name = $namespace.'\\'.$name;
                }
                $names[] = $name;
            }
        }
        $violations = [];
        foreach (array_unique($names) as $name) {
            if (! str_starts_with($name, 'App\\') || in_array($name, self::HOST, true)) {
                continue;
            }
            $segments = explode('\\', $name);
            $ok = $segments[1] === 'Starter' && isset($segments[3], self::REQUIRES[$segments[2]]) && in_array($segments[2], $allowed, true);
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
        $feature = self::JS[explode('/', $relative)[0]];
        $allowed = [$feature, 'SessionUi', ...$this->closure($feature, [])];
        preg_match_all("/(?:import|from)\\s*\\(?\\s*['\"](\\.{1,2}\\/[^'\"]+)['\"]/", $source, $matches);
        $violations = [];
        foreach ($matches[1] as $import) {
            $target = $this->normalise(dirname($relative).'/'.$import);
            $targetFeature = self::JS[explode('/', $target)[0]] ?? null;
            if ($targetFeature === null || str_starts_with($target, '..') || ! in_array($targetFeature, $allowed, true)) {
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
