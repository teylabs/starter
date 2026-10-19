<?php

namespace App\Mcp;

final class UpdateNote extends NoteTool
{
    protected string $operation = 'update';

    protected string $name = 'workspace_note_update';

    protected string $description = 'Update a note using its current version and stable intent key.';
}
