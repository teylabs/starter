<?php

use Illuminate\Support\Facades\Schedule;
use Tey\Kit\Features\Mcp\Models\McpIntent;
use Tey\Kit\Features\SessionUi\Models\SignedInDevice;

// The mcp feature's host step: stored intent results expire after kit.mcp.intent_ttl, and pruning removes the expired rows. Signed-in devices (kit 0.24)
// past session.lifetime plus kit.devices.touch_minutes are hidden from the Security page already; pruning keeps the table short.
Schedule::command('model:prune', ['--model' => [McpIntent::class, SignedInDevice::class]])->daily();
// Failed jobs are kept a week for a look, then pruned, and finished batches two days; both daily, so an app on the iterating profile can still sleep.
Schedule::command('queue:prune-failed', ['--hours' => 168])->daily();
Schedule::command('queue:prune-batches', ['--hours' => 48])->daily();
// Telescope keeps two days, so its table stays small on the iterating profile's database.
Schedule::command('telescope:prune', ['--hours' => 48])->daily();
