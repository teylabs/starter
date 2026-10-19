<?php

use App\Http\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:web', 'concierge.active'])->group(function (): void {
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/tenant-invitations/accept', [WorkspaceController::class, 'accept']);
});
