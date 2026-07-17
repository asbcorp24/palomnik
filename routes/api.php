<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\MobileController;
use App\Http\Controllers\Api\V1\PilgrimageObjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'Moscow Pilgrim API',
            'version' => 'v1',
            'time' => now()->toIso8601String(),
        ]);
    })->name('health');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    Route::prefix('directories')->name('directories.')->group(function () {
        Route::get('/object-types', [DirectoryController::class, 'objectTypes'])->name('object-types');
        Route::get('/vicariates', [DirectoryController::class, 'vicariates'])->name('vicariates');
        Route::get('/deaneries', [DirectoryController::class, 'deaneries'])->name('deaneries');
        Route::get('/sanctities', [DirectoryController::class, 'sanctities'])->name('sanctities');
    });

    Route::get('/objects', [PilgrimageObjectController::class, 'index'])->name('objects.index');
    Route::get('/objects/{pilgrimageObject:slug}', [PilgrimageObjectController::class, 'show'])->name('objects.show');

    Route::prefix('mobile')->name('mobile.')->group(function () {
        Route::get('/home', [MobileController::class, 'home'])->name('home');
        Route::get('/routes', [MobileController::class, 'routes'])->name('routes.index');
        Route::get('/routes/{pilgrimageRoute:slug}', [MobileController::class, 'route'])->name('routes.show');
        Route::get('/calendar', [MobileController::class, 'calendar'])->name('calendar.index');
        Route::get('/calendar/{calendarEvent:slug}', [MobileController::class, 'event'])->name('calendar.show');
        Route::get('/community', [MobileController::class, 'community'])->name('community.index');
        Route::get('/community/{post:slug}', [MobileController::class, 'post'])->name('community.show');
        Route::get('/together', [MobileController::class, 'together'])->name('together.index');
        Route::get('/together/{jointPilgrimage:slug}', [MobileController::class, 'joint'])->name('together.show');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/profile', [MobileController::class, 'profile'])->name('profile');
            Route::post('/profile', [MobileController::class, 'updateProfile'])->name('profile.update');
            Route::get('/favorites', [MobileController::class, 'favorites'])->name('favorites.index');
            Route::post('/favorites/{pilgrimageObject}', [MobileController::class, 'toggleFavorite'])->name('favorites.toggle');
            Route::get('/bookings', [MobileController::class, 'bookings'])->name('bookings.index');
            Route::get('/notifications', [MobileController::class, 'notifications'])->name('notifications.index');
            Route::put('/notifications/{notification}/read', [MobileController::class, 'readNotification'])->name('notifications.read');
            Route::post('/visits', [MobileController::class, 'storeVisit'])->name('visits.store');
            Route::post('/reviews', [MobileController::class, 'storeReview'])->name('reviews.store');
            Route::post('/together', [MobileController::class, 'createJoint'])->name('together.store');
            Route::post('/together/{jointPilgrimage:slug}/join', [MobileController::class, 'joinJoint'])->name('together.join');
            Route::delete('/together/{jointPilgrimage:slug}/leave', [MobileController::class, 'leaveJoint'])->name('together.leave');
            Route::post('/together/{jointPilgrimage:slug}/messages', [MobileController::class, 'storeJointMessage'])->name('together.messages.store');
            Route::get('/route-plans', [MobileController::class, 'routePlans'])->name('route-plans.index');
            Route::post('/route-plans', [MobileController::class, 'storeRoutePlan'])->name('route-plans.store');
        });
    });
});
