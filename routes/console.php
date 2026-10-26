<?php

use Illuminate\Support\Facades\Schedule;
use Tey\Kit\Features\Mcp\Models\McpIntent;

// The mcp feature's host step: stored intent results expire after kit.mcp.intent_ttl, and pruning removes the expired rows.
Schedule::command('model:prune', ['--model' => [McpIntent::class]])->daily();
