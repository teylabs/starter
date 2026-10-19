<?php

use App\Support\McpCapabilities;
use Illuminate\Foundation\Testing\TestCase;
use Tey\Kit\Features\Core\Registry;

require dirname(__DIR__).'/vendor/autoload.php';

/** docs/mcp-capabilities.json is the served MCP surface exactly: every tool, its scope and input schema. */
final class McpCapabilitiesTest extends TestCase
{
    public function createApplication()
    {
        require __DIR__.'/environment.php';

        return require dirname(__DIR__).'/bootstrap/app.php';
    }

    public function test_the_committed_inventory_matches_the_served_tools(): void
    {
        $this->assertSame(McpCapabilities::json(), file_get_contents(base_path(McpCapabilities::PATH)), 'Run php bin/mcp-capabilities.php');
        $inventory = McpCapabilities::inventory();
        $this->assertSame(1, $inventory['inventory']);
        $this->assertSame(array_keys(McpCapabilities::SCOPES), array_column($inventory['tools'], 'name'));
        $this->assertCount(count(app(Registry::class)->mcpTools()), $inventory['tools']);
        foreach ($inventory['tools'] as $tool) {
            $this->assertArrayHasKey($tool['scope'], $inventory['scopes'], $tool['name']);
        }
    }
}
