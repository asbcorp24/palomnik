<?php

use App\Http\Controllers\Admin\PointOfInterestController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'permission:content.manage'])
    ->group(function () {
        Route::resource('points-of-interest', PointOfInterestController::class)
            ->except('show')
            ->parameters(['points-of-interest' => 'pointOfInterest']);
    });
