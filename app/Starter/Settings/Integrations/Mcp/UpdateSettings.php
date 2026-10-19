<?php

namespace App\Starter\Settings\Integrations\Mcp;

use App\Starter\Mcp\Context;
use App\Starter\Settings\Operations\WorkspaceProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

final class UpdateSettings extends Tool
{
    protected string $name = 'workspace_settings_update';

    protected string $description = 'Update settings using the current version and stable intent key.';

    public function schema(JsonSchema $schema): array
    {
        return ['display_name' => $schema->string()->required(), 'timezone' => $schema->string()->required(), 'expected_version' => $schema->integer()->min(1)->required(), 'intent_key' => $schema->string()->required()];
    }

    public function handle(Request $request): mixed
    {
        try {
            return Response::structured(app(WorkspaceProfile::class)->update(Context::principal('settings.write'), $request->all())->toArray());
        } catch (RuntimeException $e) {
            return Response::error(in_array($e->getMessage(), ['unknown_arguments', 'stale_settings', 'intent_conflict', 'connection_denied', 'permission_denied', 'workspace_not_found', 'account_inactive', 'verification_required', 'admission_required', 'capability_unavailable', 'entitlement_required'], true) ? $e->getMessage() : 'operation_failed');
        }
    }
}
