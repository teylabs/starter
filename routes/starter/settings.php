<?php

use App\Http\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:web', 'concierge.active'])->group(function (): void {
    Route::get('/workspaces/{workspace}/settings', [WorkspaceController::class, 'show'])->whereNumber('workspace');
    Route::put('/workspaces/{workspace}/settings', [WorkspaceController::class, 'update'])->whereNumber('workspace');
});
