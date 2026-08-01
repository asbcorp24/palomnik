<?php

use App\Http\Controllers\Api\V1\MapObjectBySlugController;
use App\Http\Controllers\Api\V1\MapViewportController;
use Illuminate\Support\Facades\Route;

Route::get('/map/object-by-slug/{slug}', MapObjectBySlugController::class)
    ->middleware('throttle:240,1');

Route::get('/map/objects', [MapViewportController::class, 'objects'])
    ->middleware('throttle:240,1');

Route::get('/map/objects/{objectId}', [MapViewportController::class, 'object'])
    ->whereNumber('objectId')
    ->middleware('throttle:240,1');

Route::get('/map/points-of-interest', [MapViewportController::class, 'pointsOfInterest'])
    ->middleware('throttle:240,1');

Route::get('/map/points-of-interest/{pointId}', [MapViewportController::class, 'pointOfInterest'])
    ->whereNumber('pointId')
    ->middleware('throttle:240,1');
