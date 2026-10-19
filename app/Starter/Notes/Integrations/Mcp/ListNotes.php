<?php

namespace App\Starter\Notes\Integrations\Mcp;

final class ListNotes extends NoteTool
{
    protected string $operation = 'list';

    protected string $name = 'workspace_notes_list';

    protected string $description = 'List notes in the approved workspace.';
}
