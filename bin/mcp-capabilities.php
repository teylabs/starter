<?php

use App\Support\McpCapabilities;
use Illuminate\Contracts\Console\Kernel;

// Rewrites docs/mcp-capabilities.json from the live registry; McpCapabilitiesTest fails when it is stale.
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
file_put_contents(base_path(McpCapabilities::PATH), McpCapabilities::json());
