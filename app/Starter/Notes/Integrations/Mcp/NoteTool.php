<?php

namespace App\Starter\Notes\Integrations\Mcp;

use App\Starter\Mcp\Context;
use App\Starter\Notes\Operations\WorkspaceNotes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

abstract class NoteTool extends Tool
{
    protected string $operation;

    public function schema(JsonSchema $schema): array
    {
        $fields = [];
        if (in_array($this->operation, ['read', 'update', 'delete'], true)) {
            $fields['id'] = $schema->integer()->min(1)->required();
        }
        if (in_array($this->operation, ['create', 'update', 'delete'], true)) {
            $fields['intent_key'] = $schema->string()->required();
        }
        if (in_array($this->operation, ['update', 'delete'], true)) {
            $fields['expected_version'] = $schema->integer()->min(1)->required();
        }
        if (in_array($this->operation, ['create', 'update'], true)) {
            $fields['body'] = $schema->string()->required();
        }

        return $fields;
    }

    public function handle(Request $request): mixed
    {
        try {
            $principal = Context::principal(in_array($this->operation, ['list', 'read'], true) ? 'notes.read' : 'notes.write');
            $notes = app(WorkspaceNotes::class);
            $result = match ($this->operation) {
                'list' => ['notes' => array_map(fn ($note) => $note->toArray(), $notes->listing($principal, $request->all()))],
                'read' => $notes->read($principal, $request->all())->toArray(),
                default => $notes->mutate($principal, $this->operation, $request->all())->toArray(),
            };

            return Response::structured($result);
        } catch (RuntimeException $e) {
            return Response::error(in_array($e->getMessage(), ['note_not_found', 'unknown_arguments', 'stale_note', 'note_intent_conflict', 'connection_denied', 'permission_denied', 'workspace_not_found', 'account_inactive', 'verification_required', 'admission_required', 'capability_unavailable', 'entitlement_required'], true) ? $e->getMessage() : 'operation_failed');
        }
    }
}
