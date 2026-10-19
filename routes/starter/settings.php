<?php

use App\Starter\Settings\Http\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:web', 'concierge.active'])->group(function (): void {
    Route::get('/workspaces/{workspace}/settings', [SettingsController::class, 'show'])->whereNumber('workspace');
    Route::put('/workspaces/{workspace}/settings', [SettingsController::class, 'update'])->whereNumber('workspace');
});
