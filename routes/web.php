<?php

use App\Http\ConnectionController;
use App\Http\NoteController;
use App\Http\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/workspaces');
Route::middleware(['auth:web', 'concierge.active'])->group(function (): void {
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('/workspaces/{workspace}/settings', [WorkspaceController::class, 'show'])->whereNumber('workspace');
    Route::put('/workspaces/{workspace}/settings', [WorkspaceController::class, 'update'])->whereNumber('workspace');
    Route::post('/tenant-invitations/accept', [WorkspaceController::class, 'accept']);
});

Route::post('/workspaces/{workspace}/connections', [ConnectionController::class, 'store'])->whereNumber('workspace')->middleware(['auth:web', 'concierge.active']);

Route::get('/workspaces/{workspace}/connections', [ConnectionController::class, 'index'])->whereNumber('workspace')->middleware(['auth:web', 'concierge.active']);
Route::delete('/workspaces/{workspace}/connections/{connection}', [ConnectionController::class, 'destroy'])->whereNumber(['workspace', 'connection'])->middleware(['auth:web', 'concierge.active']);

Route::middleware(['auth:web', 'concierge.active'])->prefix('workspaces/{workspace}')->whereNumber('workspace')->group(function (): void {
    Route::get('notes', [NoteController::class, 'index']);
    Route::get('notes/{note}', [NoteController::class, 'show'])->whereNumber('note');
    Route::post('notes', [NoteController::class, 'store']);
    Route::put('notes/{note}', [NoteController::class, 'update'])->whereNumber('note');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->whereNumber('note');
});
