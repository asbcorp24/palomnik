<?php

use App\Http\Controllers\Site\DayRouteController;
use Illuminate\Support\Facades\Route;

Route::get('/route-of-the-day', [DayRouteController::class, 'index'])
    ->name('day-route.index');

Route::post('/route-of-the-day', [DayRouteController::class, 'generate'])
    ->middleware('throttle:20,1')
    ->name('day-route.generate');

Route::post('/route-of-the-day/save', [DayRouteController::class, 'save'])
    ->middleware(['auth', 'verified'])
    ->name('day-route.save');
