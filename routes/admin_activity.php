<?php

use App\Http\Controllers\Admin\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'permission:activity.view'])
    ->group(function (): void {
        Route::get('/activity', [ActivityLogController::class, 'index'])
            ->name('activity.index');
        Route::get('/activity/{activityLog}', [ActivityLogController::class, 'show'])
            ->name('activity.show');
        Route::post('/activity/{activityLog}/restore', [ActivityLogController::class, 'restore'])
            ->middleware('permission:system.manage')
            ->name('activity.restore');
    });
