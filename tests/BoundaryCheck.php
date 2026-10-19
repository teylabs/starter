<?php

require __DIR__.'/FoundationCheck.php';
use App\Operations\ComplimentaryAccess;
use App\Support\Principal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;

check('invalid typed setting makes no database change on either adapter', function () use ($ui, $mcp, $alpha, $connection): void {
    $before = DB::table('settings')->get()->toJson();
    foreach ([fn ($input) => $ui->update($alpha, 1, $input), fn ($input) => $mcp->call($connection, 1, 'workspace_settings_update', $input)] as $operation) {
        try {
            $operation(['display_name' => 'invalid', 'timezone' => 'invalid-zone', 'expected_version' => 3, 'intent_key' => 'invalid']);
            throw new RuntimeException('Invalid settings accepted');
        } catch (ValidationException $e) {
        }
    }
    same($before, DB::table('settings')->get()->toJson());
});
check('complimentary access cannot bypass capability off or missing membership', function () use ($ui, $mcp, $alpha, $connection, $operator): void {
    app(ComplimentaryAccess::class)->grant($operator, 1, 'capability fixture', now()->addDay(), 'capability-off');
    $before = DB::table('settings')->get()->toJson();
    config(['foundation.capabilities' => ['workspace.settings' => false]]);
    Feature::flushCache();
    refused('capability_unavailable', fn () => $ui->show($alpha, 1));
    refused('capability_unavailable', fn () => $mcp->call($connection, 1, 'workspace_settings_get'));
    refused('workspace_not_found', fn () => $ui->show(new Principal(2), 1));
    same($before, DB::table('settings')->get()->toJson());
    config(['foundation.capabilities' => ['workspace.settings' => true]]);
    Feature::flushCache();
});
