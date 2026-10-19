<?php

namespace App\Mcp;

use Laravel\Mcp\Server;

final class WorkspaceServer extends Server
{
    protected string $name = 'Workspace operations';

    protected string $version = '0.1.0';

    protected string $instructions = 'Read and update the approved workspace settings. Writes require the current version and a stable intent key.';

    protected array $tools = [ReadSettings::class, UpdateSettings::class, ListNotes::class, ReadNote::class, CreateNote::class, UpdateNote::class, DeleteNote::class];
}
