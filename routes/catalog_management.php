<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ObjectBulkActionController;
use App\Http\Controllers\Admin\ObjectDuplicateController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'permission:content.manage'])
    ->group(function (): void {
        Route::post('/objects/bulk', ObjectBulkActionController::class)
            ->name('objects.bulk');

        Route::get('/duplicates', [ObjectDuplicateController::class, 'index'])
            ->name('duplicates.index');
        Route::post('/duplicates/scan', [ObjectDuplicateController::class, 'scan'])
            ->middleware('throttle:3,10')
            ->name('duplicates.scan');
        Route::post('/duplicates/{candidate}/mark', [ObjectDuplicateController::class, 'mark'])
            ->name('duplicates.mark');
        Route::post('/duplicates/{candidate}/parent', [ObjectDuplicateController::class, 'setParent'])
            ->name('duplicates.parent');
        Route::post('/duplicates/{candidate}/merge', [ObjectDuplicateController::class, 'merge'])
            ->name('duplicates.merge');

        Route::get('/analytics', [AnalyticsController::class, 'index'])
            ->name('analytics.index');
    });
