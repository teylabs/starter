<?php

use App\Http\ConnectionController;
use Illuminate\Support\Facades\Route;

Route::post('/workspaces/{workspace}/connections', [ConnectionController::class, 'store'])->whereNumber('workspace')->middleware(['auth:web', 'concierge.active']);

Route::get('/workspaces/{workspace}/connections', [ConnectionController::class, 'index'])->whereNumber('workspace')->middleware(['auth:web', 'concierge.active']);
Route::delete('/workspaces/{workspace}/connections/{connection}', [ConnectionController::class, 'destroy'])->whereNumber(['workspace', 'connection'])->middleware(['auth:web', 'concierge.active']);
