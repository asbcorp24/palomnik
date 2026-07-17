<?php

use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\PilgrimageObjectController;
use Illuminate\Http\Request;
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

    Route::prefix('directories')->name('directories.')->group(function () {
        Route::get('/object-types', [DirectoryController::class, 'objectTypes'])->name('object-types');
        Route::get('/vicariates', [DirectoryController::class, 'vicariates'])->name('vicariates');
        Route::get('/deaneries', [DirectoryController::class, 'deaneries'])->name('deaneries');
        Route::get('/sanctities', [DirectoryController::class, 'sanctities'])->name('sanctities');
    });

    Route::get('/objects', [PilgrimageObjectController::class, 'index'])->name('objects.index');
    Route::get('/objects/{pilgrimageObject:slug}', [PilgrimageObjectController::class, 'show'])
        ->name('objects.show');

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    })->name('user');
});
