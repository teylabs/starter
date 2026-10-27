<?php

namespace App\Support;

use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use RuntimeException;
use Tey\Kit\Dashboard\ReadDashboard;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\WorkspaceServer;
use Tey\Kit\Features\Notes\Integrations\Mcp\DeleteNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\ListNotes;
use Tey\Kit\Features\Notes\Integrations\Mcp\ReadNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\WriteNote;
use Tey\Kit\Features\Settings\Integrations\Mcp\ReadSettings;
use Tey\Kit\Features\Settings\Integrations\Mcp\UpdateSettings;
use Tey\Kit\Search\SearchWorkspace;

/**
 * The MCP capability declaration (version 1, docs/schemas/mcp-capabilities.schema.json) committed as
 * docs/mcp-capabilities.json. Every path is a file in this checkout (the kit's under vendor/tey/kit) and every symbol
 * a function, class or const that file declares; McpCapabilitiesTest checks both and validates the schema.
 */
final class McpCapabilities
{
    public const PATH = 'docs/mcp-capabilities.json';

    public const SCHEMA = 'docs/schemas/mcp-capabilities.schema.json';

    private const KIT = 'vendor/tey/kit/src/Features/';

    private const SETTINGS_PAGE = 'resources/js/pages/Workspace/Settings/General/Page.vue';

    private const SETTINGS_CONTROLLER = self::KIT.'Settings/Http/SettingsController.php';

    private const DASHBOARD_PAGE = 'resources/js/pages/Workspace/Dashboard/Page.vue';

    private const DASHBOARD_CONTROLLER = self::KIT.'SessionUi/Http/DashboardController.php';

    private const SEARCH_PAGE = 'resources/js/pages/Workspace/Search/Page.vue';

    private const SEARCH_CONTROLLER = self::KIT.'SessionUi/Http/SearchController.php';

    private const NOTES_PAGE = 'resources/js/pages/Workspace/Notes/Index/Page.vue';

    private const NOTES_CONTROLLER = self::KIT.'Notes/Http/NoteController.php';

    private const NOTES = self::KIT.'Notes/Operations/WorkspaceNotes.php';

    /** UI source file (each page and the kit controller that serves it) => the capabilities it reaches. */
    public const INVENTORY = [
        self::SETTINGS_PAGE => ['workspace.settings.read', 'workspace.settings.update'],
        self::SETTINGS_CONTROLLER => ['workspace.settings.read', 'workspace.settings.update'],
        self::DASHBOARD_PAGE => ['workspace.dashboard.read'],
        self::DASHBOARD_CONTROLLER => ['workspace.dashboard.read'],
        self::SEARCH_PAGE => ['workspace.search.read'],
        self::SEARCH_CONTROLLER => ['workspace.search.read'],
        self::NOTES_PAGE => ['workspace.notes.list', 'workspace.notes.read', 'workspace.notes.write', 'workspace.notes.delete'],
        self::NOTES_CONTROLLER => ['workspace.notes.list', 'workspace.notes.read', 'workspace.notes.write', 'workspace.notes.delete'],
    ];

    /** The parity tests: name => [path, what they cover on both transports]. */
    private const TESTS = [
        'session_settings' => ['tests/TransportTest.php', 'test_session_settings', ['success', 'authorization', 'retry', 'stale', 'audit', 'no_bypass', 'error', 'recovery']],
        'delegated_settings' => ['tests/McpTransportTest.php', 'test_delegated_settings', ['success', 'authorization', 'validation', 'retry', 'stale', 'audit', 'privacy', 'no_bypass', 'error', 'recovery']],
        'session_dashboard' => ['tests/DashboardTest.php', 'test_session_dashboard', ['success', 'authorization', 'privacy', 'no_bypass']],
        'session_search' => ['tests/SearchTest.php', 'test_session_search', ['success', 'authorization', 'privacy', 'no_bypass', 'error']],
        'session_notes' => ['tests/NotesTest.php', 'test_session_notes', ['success', 'authorization', 'retry', 'stale', 'audit', 'no_bypass']],
        'inertia_pages' => ['tests/InertiaTransportTest.php', 'test_inertia_session_pages_share_real_operations_and_fresh_navigation', ['success', 'authorization', 'validation', 'retry', 'stale', 'error']],
    ];

    /** Capability id => [kind, UI references, domain operation, MCP tool class, scope, tests, effects]. */
    private const CAPABILITIES = [
        'workspace.settings.read' => ['read', [[self::SETTINGS_PAGE, 'props'], [self::SETTINGS_CONTROLLER, 'show']], [self::KIT.'Settings/Operations/WorkspaceProfile.php', 'read'], ReadSettings::class, 'settings.read', ['session_settings', 'delegated_settings', 'inertia_pages'], ['returns the display name, timezone and version']],
        'workspace.settings.update' => ['write', [[self::SETTINGS_PAGE, 'props'], [self::SETTINGS_CONTROLLER, 'update']], [self::KIT.'Settings/Operations/WorkspaceProfile.php', 'update'], UpdateSettings::class, 'settings.write', ['session_settings', 'delegated_settings', 'inertia_pages'], ['writes the settings at the expected version', 'audits workspace.settings.updated once per intent key']],
        'workspace.dashboard.read' => ['read', [[self::DASHBOARD_PAGE, 'props'], [self::DASHBOARD_CONTROLLER, 'show']], [self::KIT.'SessionUi/Dashboard/Widgets.php', 'resolve'], ReadDashboard::class, 'dashboard.read', ['session_dashboard', 'delegated_settings'], ['returns the workspace and the widgets the person may see, each gated by its own ability']],
        'workspace.search.read' => ['read', [[self::SEARCH_PAGE, 'props'], [self::SEARCH_CONTROLLER, 'index']], [self::KIT.'SessionUi/Search/Searches.php', 'run'], SearchWorkspace::class, 'search.read', ['session_search', 'delegated_settings'], ['returns each registered provider\'s results the person may see in the workspace, grouped, with partial when a provider failed']],
        'workspace.notes.list' => ['read', [[self::NOTES_PAGE, 'props'], [self::NOTES_CONTROLLER, 'index']], [self::NOTES, 'listing'], ListNotes::class, 'notes.read', ['session_notes', 'delegated_settings'], ['returns the newest notes, pinned first']],
        'workspace.notes.read' => ['read', [[self::NOTES_PAGE, 'props'], [self::NOTES_CONTROLLER, 'show']], [self::NOTES, 'read'], ReadNote::class, 'notes.read', ['session_notes', 'delegated_settings'], ['returns one note with its version']],
        'workspace.notes.write' => ['write', [[self::NOTES_PAGE, 'props'], [self::NOTES_CONTROLLER, 'store'], [self::NOTES_CONTROLLER, 'update']], [self::NOTES, 'mutate'], WriteNote::class, 'notes.write', ['session_notes', 'delegated_settings'], ['creates a note, or changes your own (or any, with moderation) at the expected version', 'audits workspace.note.created or workspace.note.updated once per intent key']],
        'workspace.notes.delete' => ['write', [[self::NOTES_PAGE, 'props'], [self::NOTES_CONTROLLER, 'destroy']], [self::NOTES, 'mutate'], DeleteNote::class, 'notes.write', ['session_notes', 'delegated_settings'], ['deletes your own note (or any, with moderation) at the expected version, after confirmation', 'audits workspace.note.deleted once per intent key']],
    ];

    public static function inventory(): array
    {
        $served = [];
        foreach (app(Registry::class)->mcpTools() as $class) {
            $served[$class] = app($class);
        }
        $capabilities = [];
        foreach (self::CAPABILITIES as $id => [$kind, $ui, $domain, $class, $scope, $tests, $effects]) {
            /** @var Tool $tool */
            $tool = $served[$class] ?? throw new RuntimeException('Capability '.$id.' names a tool the server does not serve: '.$class.'.');
            $definition = $tool->toArray();
            $evidence = array_map(fn (string $test): array => ['path' => self::TESTS[$test][0], 'name' => self::TESTS[$test][1], 'covers' => self::TESTS[$test][2]], $tests);
            $inputs = self::inputs($definition['inputSchema']);
            $capabilities[] = [
                'id' => $id,
                'kind' => $kind,
                'ui' => array_map(fn (array $reference): array => ['path' => $reference[0], 'symbol' => $reference[1]], $ui),
                'domain' => ['path' => $domain[0], 'symbol' => $domain[1]],
                'mcp' => [
                    'status' => 'implemented',
                    'kind' => 'tool',
                    'name' => $definition['name'],
                    'entrypoint' => ['path' => self::path($class), 'symbol' => (new ReflectionClass($class))->getShortName()],
                    'scopes' => ['mcp:use', $scope],
                    'description' => $definition['description'],
                    'input_schema' => $definition['inputSchema'],
                ],
                'tests' => $evidence,
                'variants' => [[
                    'id' => 'default',
                    // The UI and MCP take the same canonical inputs, defaults and effects: they call one operation.
                    'ui' => ['options' => $inputs, 'defaults' => (object) [], 'effects' => $effects],
                    'mcp' => ['options' => $inputs, 'defaults' => (object) [], 'effects' => $effects, 'status' => 'implemented'],
                    'tests' => $evidence,
                ]],
            ];
        }
        $unmapped = array_diff(array_keys($served), array_column(self::CAPABILITIES, 3));
        if ($unmapped !== []) {
            throw new RuntimeException('MCP tools without a capability: '.implode(', ', $unmapped).'.');
        }
        $server = (new ReflectionClass(WorkspaceServer::class))->getDefaultProperties();

        return [
            'version' => 1,
            'inventory' => array_map(fn (string $path, array $ids): array => ['path' => $path, 'capabilities' => $ids, 'variants' => array_fill_keys($ids, ['default'])], array_keys(self::INVENTORY), self::INVENTORY),
            'capabilities' => $capabilities,
            'server' => [
                'name' => $server['name'],
                'version' => $server['version'],
                'transport' => 'streamable-http',
                'endpoint' => '/mcp/connections/{connection}',
                'entrypoint' => ['path' => self::path(WorkspaceServer::class), 'symbol' => 'WorkspaceServer'],
                'auth' => ['type' => 'oauth2', 'flow' => 'authorization_code_pkce', 'dynamic_client_registration' => false, 'binding' => 'one approved workspace connection per token'],
                'scopes' => app(Registry::class)->tokenScopes(),
            ],
        ];
    }

    public static function json(): string
    {
        return json_encode(self::inventory(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }

    /** The canonical inputs of an operation, from its tool's input schema: name => "type, required|optional". */
    private static function inputs(array $schema): object
    {
        $required = $schema['required'] ?? [];
        $inputs = [];
        foreach ((array) ($schema['properties'] ?? []) as $name => $property) {
            $inputs[$name] = ((array) $property)['type'].', '.(in_array($name, $required, true) ? 'required' : 'optional');
        }

        return (object) $inputs;
    }

    /** A class's file, relative to the checkout. */
    private static function path(string $class): string
    {
        $file = (string) (new ReflectionClass($class))->getFileName();
        // TEMP: the kit is a symlinked path repo, so its real path is outside the checkout.
        $kit = (string) realpath(base_path('vendor/tey/kit'));
        if (str_starts_with($file, $kit.'/')) {
            return 'vendor/tey/kit/'.substr($file, strlen($kit) + 1);
        }

        return substr($file, strlen(base_path()) + 1);
    }
}
