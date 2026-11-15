<?php

use Illuminate\Support\Facades\Schedule;
use Tey\Kit\Features\Mcp\Models\McpIntent;
use Tey\Kit\Features\SessionUi\Models\SignedInDevice;

// The mcp feature's host step: stored intent results expire after kit.mcp.intent_ttl, and pruning removes the expired rows. Signed-in devices (kit 0.24)
// past session.lifetime plus kit.devices.touch_minutes are hidden from the Security page already; pruning keeps the table short.
Schedule::command('model:prune', ['--model' => [McpIntent::class, SignedInDevice::class]])->daily();
