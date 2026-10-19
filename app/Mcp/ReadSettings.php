<?php

namespace App\Mcp;

use App\Operations\WorkspaceProfile;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

final class ReadSettings extends Tool
{
    protected string $name = 'workspace_settings_read';

    protected string $description = 'Read settings and current version of the approved workspace.';

    public function handle(Request $request): mixed
    {
        if ($request->all() !== []) {
            return Response::error('unknown_arguments');
        }
        try {
            return Response::structured(app(WorkspaceProfile::class)->read(Context::principal('settings.read'))->toArray());
        } catch (RuntimeException $e) {
            return Response::error(in_array($e->getMessage(), ['connection_denied', 'workspace_not_found', 'account_inactive', 'verification_required', 'admission_required', 'capability_unavailable', 'entitlement_required'], true) ? $e->getMessage() : 'operation_failed');
        }
    }
}
