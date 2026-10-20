<?php

namespace App\Support;

use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use RuntimeException;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\WorkspaceServer;

/** The MCP capability inventory (v1) committed as docs/mcp-capabilities.json, built from the live registry. */
final class McpCapabilities
{
    public const PATH = 'docs/mcp-capabilities.json';

    /** Tool name => the delegated scope its operation requires (Context::principal), for the tools the template serves. */
    public const SCOPES = [
        'workspace_settings_read' => 'settings.read',
        'workspace_settings_update' => 'settings.write',
        'workspace_notes_list' => 'notes.read',
        'workspace_note_read' => 'notes.read',
        'workspace_note_create' => 'notes.write',
        'workspace_note_update' => 'notes.write',
        'workspace_note_delete' => 'notes.write',
    ];

    public static function inventory(): array
    {
        $registry = app(Registry::class);
        $server = new ReflectionClass(WorkspaceServer::class);
        $defaults = $server->getDefaultProperties();
        $tools = [];
        foreach ($registry->mcpTools() as $class) {
            /** @var Tool $tool */
            $tool = app($class);
            $definition = $tool->toArray();
            $scope = self::SCOPES[$definition['name']] ?? throw new RuntimeException('No scope is declared for MCP tool '.$definition['name'].'.');
            $tools[] = ['name' => $definition['name'], 'description' => $definition['description'], 'scope' => $scope, 'writes' => str_ends_with($scope, '.write'), 'input_schema' => $definition['inputSchema']];
        }

        return [
            'inventory' => 1,
            'server' => [
                'name' => $defaults['name'],
                'version' => $defaults['version'],
                'transport' => 'streamable-http',
                'endpoint' => '/mcp/connections/{connection}',
                'auth' => ['type' => 'oauth2', 'flow' => 'authorization_code_pkce', 'dynamic_client_registration' => false, 'binding' => 'one approved workspace connection per token'],
            ],
            'scopes' => $registry->tokenScopes(),
            'tools' => $tools,
            'resources' => [],
            'prompts' => [],
        ];
    }

    public static function json(): string
    {
        return json_encode(self::inventory(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }
}
