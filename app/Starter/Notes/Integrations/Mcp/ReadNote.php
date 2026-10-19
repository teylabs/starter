<?php

namespace App\Starter\Notes\Integrations\Mcp;

final class ReadNote extends NoteTool
{
    protected string $operation = 'read';

    protected string $name = 'workspace_note_read';

    protected string $description = 'Read one note in the approved workspace.';
}
