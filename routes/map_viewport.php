<?php

use App\Http\Controllers\Api\V1\MapViewportController;
use Illuminate\Support\Facades\Route;

Route::get('/map/objects', [MapViewportController::class, 'objects'])
    ->middleware('throttle:240,1')
    ->name('map.objects');

Route::get('/map/objects/{objectId}', [MapViewportController::class, 'object'])
    ->whereNumber('objectId')
    ->middleware('throttle:240,1')
    ->name('map.object');

Route::get('/map/points-of-interest', [MapViewportController::class, 'pointsOfInterest'])
    ->middleware('throttle:240,1')
    ->name('map.points-of-interest');

Route::get('/map/points-of-interest/{pointId}', [MapViewportController::class, 'pointOfInterest'])
    ->whereNumber('pointId')
    ->middleware('throttle:240,1')
    ->name('map.point-of-interest');
