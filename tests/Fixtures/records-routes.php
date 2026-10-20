<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\RecordsController;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;

// The test-only sample resource's routes: the same shape a feature's route file has.
WorkspaceRoutes::define(function (bool $full): void {
    WorkspaceRoutes::page(Route::get('records', [RecordsController::class, 'index']), $full, 'records', 'workspaces.records.index', 'Records', ['session-ui', 'home']);
    WorkspaceRoutes::page(Route::get('records/{record}', [RecordsController::class, 'show'])->whereNumber('record'), $full, 'records', 'workspaces.records.show', static fn (int $workspace, int $record): string => (string) (DB::table('fixture_records')->where('workspace_id', $workspace)->where('id', $record)->value('title') ?? 'Record'), ['records', 'workspaces.records.index']);
    Route::post('records', [RecordsController::class, 'store']);
    Route::put('records/{record}', [RecordsController::class, 'update'])->whereNumber('record');
    Route::delete('records/{record}', [RecordsController::class, 'destroy'])->whereNumber('record');
});
