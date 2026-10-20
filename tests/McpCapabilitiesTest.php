<?php

use App\Support\McpCapabilities;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Core\Registry;

require dirname(__DIR__).'/vendor/autoload.php';

/** docs/mcp-capabilities.json follows the cookbook's version 1 schema and is the served MCP surface exactly. */
final class McpCapabilitiesTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    public function test_the_committed_inventory_matches_the_served_tools(): void
    {
        $this->assertSame(McpCapabilities::json(), file_get_contents(base_path(McpCapabilities::PATH)), 'Run php bin/mcp-capabilities.php');
    }

    public function test_the_inventory_has_the_schema_shape(): void
    {
        $document = json_decode(file_get_contents(base_path(McpCapabilities::PATH)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $document['version']);
        $this->assertIsList($document['inventory']);
        $this->assertIsList($document['capabilities']);
        $ids = array_column($document['capabilities'], 'id');
        $this->assertSame($ids, array_unique($ids));
        $routes = array_map(fn ($route) => '/'.ltrim($route->uri(), '/'), iterator_to_array(Route::getRoutes()));
        foreach ($document['inventory'] as $entry) {
            $this->assertSame(['path', 'capabilities', 'variants'], array_keys($entry));
            $this->assertContains($entry['path'], $routes, $entry['path'].' is not a route');
            $this->assertSame([], array_diff($entry['capabilities'], $ids), $entry['path']);
            $this->assertNotSame([], $entry['variants']);
        }
        $served = array_map(fn (string $class): string => app($class)->name(), app(Registry::class)->mcpTools());
        $declared = [];
        foreach ($document['capabilities'] as $capability) {
            $this->assertIsString($capability['operation']);
            $this->assertIsList($capability['mcp']['resources']);
            $this->assertArrayHasKey($capability['scope'], $document['server']['scopes'], $capability['id']);
            foreach ($capability['parity_tests'] as $test) {
                $this->assertFileExists(base_path($test));
            }
            array_push($declared, ...array_column($capability['mcp']['tools'], 'name'));
        }
        $this->assertEqualsCanonicalizing($served, $declared);
        // Every capability is reachable from a UI entry point.
        $this->assertSame([], array_diff($ids, array_merge(...array_column($document['inventory'], 'capabilities'))));
    }
}
