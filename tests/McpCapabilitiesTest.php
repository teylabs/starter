<?php

use App\Support\McpCapabilities;
use Illuminate\Foundation\Testing\TestCase;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Tey\Kit\Features\Core\Registry;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * docs/mcp-capabilities.json validates against the vendored schema (docs/schemas/mcp-capabilities.schema.json), every
 * path and symbol it names exists, and it is the served MCP surface exactly.
 */
final class McpCapabilitiesTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    public function test_the_committed_declaration_matches_the_served_tools(): void
    {
        $this->assertSame(McpCapabilities::json(), file_get_contents(base_path(McpCapabilities::PATH)), 'Run php bin/mcp-capabilities.php');
    }

    public function test_the_declaration_validates_against_the_vendored_schema(): void
    {
        $this->assertSame([], $this->schemaErrors(json_decode(file_get_contents(base_path(McpCapabilities::PATH)))));
        // The validator refuses what the schema refuses.
        $this->assertNotSame([], $this->schemaErrors(json_decode('{"version":1,"inventory":[{"path":"/workspaces","capabilities":[],"variants":["inertia"]}],"capabilities":[]}')));
    }

    public function test_every_path_symbol_and_capability_exists(): void
    {
        $document = json_decode(file_get_contents(base_path(McpCapabilities::PATH)), true, flags: JSON_THROW_ON_ERROR);
        $ids = array_column($document['capabilities'], 'id');
        $this->assertSame($ids, array_unique($ids));
        foreach ($document['inventory'] as $entry) {
            $this->assertFileExists(base_path($entry['path']));
            $this->assertSame([], array_diff($entry['capabilities'], $ids), $entry['path']);
        }
        $served = array_map(fn (string $class): string => app($class)->name(), app(Registry::class)->mcpTools());
        foreach ($document['capabilities'] as $capability) {
            foreach ([...$capability['ui'], $capability['domain'], $capability['mcp']['entrypoint']] as $reference) {
                $this->assertDeclares($reference['path'], $reference['symbol']);
            }
            $this->assertContains($capability['mcp']['name'], $served);
            foreach ($capability['mcp']['scopes'] as $scope) {
                $this->assertArrayHasKey($scope, $document['server']['scopes'], $capability['id']);
            }
            foreach ([...$capability['tests'], ...array_merge(...array_column($capability['variants'], 'tests'))] as $test) {
                $this->assertDeclares($test['path'], $test['name']);
            }
        }
        $this->assertEqualsCanonicalizing($served, array_column(array_column($document['capabilities'], 'mcp'), 'name'));
        $this->assertSame([], array_diff($ids, array_merge(...array_column($document['inventory'], 'capabilities'))), 'Every capability is reachable from a UI file');
        $this->assertDeclares($document['server']['entrypoint']['path'], $document['server']['entrypoint']['symbol']);
    }

    public function test_the_declaration_is_semantically_consistent(): void
    {
        $document = json_decode(file_get_contents(base_path(McpCapabilities::PATH)), true, flags: JSON_THROW_ON_ERROR);
        $variants = [];
        foreach ($document['capabilities'] as $capability) {
            $variants[$capability['id']] = array_column($capability['variants'], 'id');
            foreach ($capability['variants'] as $variant) {
                // The UI and MCP take the same canonical inputs, with the same defaults and effects.
                foreach (['options', 'defaults', 'effects'] as $key) {
                    $this->assertSame($this->normalize($variant['ui'][$key]), $this->normalize($variant['mcp'][$key]), $capability['id'].' '.$variant['id'].' '.$key);
                }
                $covers = array_merge(...array_column($variant['tests'], 'covers'));
                foreach (['success', 'error', 'recovery'] as $required) {
                    $this->assertContains($required, $covers, $capability['id'].' '.$variant['id'].' covers '.$required);
                }
            }
        }
        foreach ($document['inventory'] as $entry) {
            // Variants map each capability of the entry to that capability's variant ids.
            $this->assertEqualsCanonicalizing($entry['capabilities'], array_keys($entry['variants']), $entry['path']);
            foreach ($entry['variants'] as $id => $ids) {
                $this->assertSame([], array_diff($ids, $variants[$id] ?? []), $entry['path'].' '.$id);
            }
        }
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->normalize(...), $value);
    }

    /** @return array<string, mixed> */
    private function schemaErrors(mixed $data): array
    {
        $validator = new Validator;
        $validator->setMaxErrors(20);
        $result = $validator->validate($data, file_get_contents(base_path(McpCapabilities::SCHEMA)));

        return $result->isValid() ? [] : (new ErrorFormatter)->format($result->error());
    }

    /** The file exists and declares the symbol: a function, class or const. */
    private function assertDeclares(string $path, string $symbol): void
    {
        $this->assertFileExists(base_path($path));
        $this->assertMatchesRegularExpression('/\b(function|class|const)\s+'.preg_quote($symbol, '/').'\b/', file_get_contents(base_path($path)), $path.' declares '.$symbol);
    }
}
