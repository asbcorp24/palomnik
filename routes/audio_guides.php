<?php

use App\Http\Controllers\Admin\AudioGuideController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
    Route::middleware('permission:content.manage')->group(function () {
        Route::put('/objects/{object}/audio-guide', [AudioGuideController::class, 'updateObject'])
            ->name('objects.audio-guide.update');
        Route::delete('/objects/{object}/audio-guide', [AudioGuideController::class, 'destroyObject'])
            ->name('objects.audio-guide.destroy');
    });

    Route::middleware('permission:routes.manage')->group(function () {
        Route::put('/routes/{pilgrimageRoute}/audio-guide', [AudioGuideController::class, 'updateRoute'])
            ->name('routes.audio-guide.update');
        Route::delete('/routes/{pilgrimageRoute}/audio-guide', [AudioGuideController::class, 'destroyRoute'])
            ->name('routes.audio-guide.destroy');
    });
});
