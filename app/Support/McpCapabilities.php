<?php

namespace App\Support;

use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use RuntimeException;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\WorkspaceServer;

/**
 * The MCP capability inventory (the shared capability schema, version 1) committed as docs/mcp-capabilities.json: each UI
 * entry point with the shared operations it reaches, and each operation with its MCP tools and parity tests.
 */
final class McpCapabilities
{
    public const PATH = 'docs/mcp-capabilities.json';

    /** Operation => its MCP tools, the delegated scope they need, and the tests that prove session and MCP parity. */
    public const CAPABILITIES = [
        'workspace.settings.read' => ['operation' => 'Tey\\Kit\\Features\\Settings\\Operations\\WorkspaceProfile::read', 'tools' => ['workspace_settings_read'], 'scope' => 'settings.read', 'tests' => ['tests/TransportTest.php', 'tests/McpTransportTest.php']],
        'workspace.settings.update' => ['operation' => 'Tey\\Kit\\Features\\Settings\\Operations\\WorkspaceProfile::update', 'tools' => ['workspace_settings_update'], 'scope' => 'settings.write', 'tests' => ['tests/TransportTest.php', 'tests/McpTransportTest.php']],
        'workspace.notes.read' => ['operation' => 'Tey\\Kit\\Features\\Notes\\Operations\\WorkspaceNotes::listing', 'tools' => ['workspace_notes_list', 'workspace_note_read'], 'scope' => 'notes.read', 'tests' => ['tests/NotesTransportTest.php', 'tests/McpTransportTest.php']],
        'workspace.notes.write' => ['operation' => 'Tey\\Kit\\Features\\Notes\\Operations\\WorkspaceNotes::mutate', 'tools' => ['workspace_note_create', 'workspace_note_update', 'workspace_note_delete'], 'scope' => 'notes.write', 'tests' => ['tests/NotesTransportTest.php', 'tests/McpTransportTest.php']],
    ];

    /** UI entry point => the capabilities it reaches and the variants it serves. */
    public const INVENTORY = [
        '/workspaces' => [[], ['inertia', 'json']],
        '/workspaces/{workspace}/settings' => [['workspace.settings.read', 'workspace.settings.update'], ['inertia', 'json']],
        '/workspaces/{workspace}/notes' => [['workspace.notes.read', 'workspace.notes.write'], ['inertia', 'json']],
        '/workspaces/{workspace}/connections' => [[], ['blade']],
    ];

    public static function inventory(): array
    {
        $tools = [];
        foreach (app(Registry::class)->mcpTools() as $class) {
            /** @var Tool $tool */
            $tool = app($class);
            $tools[$tool->name()] = $tool->toArray();
        }
        $capabilities = [];
        foreach (self::CAPABILITIES as $id => $capability) {
            $missing = array_diff($capability['tools'], array_keys($tools));
            if ($missing !== []) {
                throw new RuntimeException('Capability '.$id.' names tools the server does not serve: '.implode(', ', $missing).'.');
            }
            $capabilities[] = [
                'id' => $id,
                'operation' => $capability['operation'],
                'mcp' => [
                    'tools' => array_map(fn (string $name): array => ['name' => $name, 'description' => $tools[$name]['description'], 'input_schema' => $tools[$name]['inputSchema']], $capability['tools']),
                    'resources' => [],
                ],
                'scope' => $capability['scope'],
                'writes' => str_ends_with($capability['scope'], '.write'),
                'parity_tests' => $capability['tests'],
            ];
        }
        $unmapped = array_diff(array_keys($tools), array_merge(...array_column(self::CAPABILITIES, 'tools')));
        if ($unmapped !== []) {
            throw new RuntimeException('MCP tools without a capability: '.implode(', ', $unmapped).'.');
        }
        $server = (new ReflectionClass(WorkspaceServer::class))->getDefaultProperties();

        return [
            'version' => 1,
            'inventory' => array_map(fn (string $path, array $entry): array => ['path' => $path, 'capabilities' => $entry[0], 'variants' => $entry[1]], array_keys(self::INVENTORY), self::INVENTORY),
            'capabilities' => $capabilities,
            'server' => [
                'name' => $server['name'],
                'version' => $server['version'],
                'transport' => 'streamable-http',
                'endpoint' => '/mcp/connections/{connection}',
                'auth' => ['type' => 'oauth2', 'flow' => 'authorization_code_pkce', 'dynamic_client_registration' => false, 'binding' => 'one approved workspace connection per token'],
                'scopes' => app(Registry::class)->tokenScopes(),
            ],
        ];
    }

    public static function json(): string
    {
        return json_encode(self::inventory(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }
}
