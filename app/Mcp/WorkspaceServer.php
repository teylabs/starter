<?php

namespace App\Mcp;

use App\Support\StarterRegistry;
use Laravel\Mcp\Server;

final class WorkspaceServer extends Server
{
    protected string $name = 'Workspace operations';

    protected string $version = '0.1.0';

    protected string $instructions = 'Read and update the approved workspace settings. Writes require the current version and a stable intent key.';

    protected function boot(): void
    {
        $this->tools = app(StarterRegistry::class)->mcpTools();
    }
}
