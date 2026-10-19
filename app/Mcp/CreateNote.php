<?php

namespace App\Mcp;

final class CreateNote extends NoteTool
{
    protected string $operation = 'create';

    protected string $name = 'workspace_note_create';

    protected string $description = 'Create a note with a stable intent key.';
}
