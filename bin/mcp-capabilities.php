<?php

use App\Support\McpCapabilities;

// Rewrites docs/mcp-capabilities.json from the live registry; McpCapabilitiesTest fails when it is stale.
$app = require dirname(__DIR__).'/bootstrap/app.php';
file_put_contents(base_path(McpCapabilities::PATH), McpCapabilities::json());
