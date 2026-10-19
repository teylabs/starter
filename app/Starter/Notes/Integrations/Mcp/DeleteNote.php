<?php

namespace App\Starter\Notes\Integrations\Mcp;

final class DeleteNote extends NoteTool
{
    protected string $operation = 'delete';

    protected string $name = 'workspace_note_delete';

    protected string $description = 'Delete a note using its current version and stable intent key.';
}
