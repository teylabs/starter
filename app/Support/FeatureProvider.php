<?php

namespace App\Support;

use App\Mcp\CreateNote;
use App\Mcp\DeleteNote;
use App\Mcp\ListNotes;
use App\Mcp\ReadNote;
use App\Mcp\ReadSettings;
use App\Mcp\UpdateNote;
use App\Mcp\UpdateSettings;
use Illuminate\Support\ServiceProvider;

/** Each starter feature's contributions to the shared seams, in a fixed order: core, workspaces, access, mcp, settings, notes. */
final class FeatureProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StarterRegistry::class, function (): StarterRegistry {
            $registry = new StarterRegistry;
            $routes = $this->app->basePath('routes/starter');

            // core
            $registry->errors(['workspace_not_found' => 404, 'workspace_mismatch' => 404, 'account_inactive' => 403, 'verification_required' => 403, 'admission_required' => 403, 'permission_denied' => 403, 'intent_conflict' => 409, 'unknown_arguments' => 422]);

            // workspaces
            $registry->routes($routes.'/workspaces.php');

            // access
            $registry->errors(['capability_unavailable' => 403, 'entitlement_required' => 403]);

            // mcp
            $registry->routes($routes.'/connections.php')
                ->routes($routes.'/mcp.php', null)
                ->scopes(['mcp:use' => 'Use workspace MCP'])
                ->errors(['connection_denied' => 403, 'connection_intent_conflict' => 409, 'connection_revoked' => 409, 'connection_active' => 409]);

            // settings
            $registry->routes($routes.'/settings.php')
                ->tools([ReadSettings::class, UpdateSettings::class])
                ->navigation('settings.read', 'Settings', 'settings')
                ->scopes(['settings.read' => 'Read workspace settings', 'settings.write' => 'Update workspace settings'])
                ->capabilities(['workspace.settings'])
                ->errors(['stale_settings' => 409], ['stale_settings' => 'These settings changed since you opened the form. Reload and review the current values.']);

            // notes
            $registry->routes($routes.'/notes.php')
                ->tools([ListNotes::class, ReadNote::class, CreateNote::class, UpdateNote::class, DeleteNote::class])
                ->navigation('notes.read', 'Notes', 'notes')
                ->scopes(['notes.read' => 'Read workspace notes', 'notes.write' => 'Create, update and delete workspace notes'])
                ->capabilities(['workspace.notes'])
                ->errors(['note_not_found' => 404, 'stale_note' => 409, 'note_intent_conflict' => 409], ['stale_note' => 'This note changed since you opened the form. Reload and review it before trying again.']);

            return $registry;
        });
    }
}
