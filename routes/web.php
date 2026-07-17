<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DirectoryController as AdminDirectoryController;
use App\Http\Controllers\Admin\ObjectMediaController as AdminObjectMediaController;
use App\Http\Controllers\Admin\PilgrimageObjectController as AdminPilgrimageObjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('admin.login.submit');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin'])
    ->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::resource('objects', AdminPilgrimageObjectController::class)
            ->parameters(['objects' => 'object']);

        Route::post('/objects/{object}/media', [AdminObjectMediaController::class, 'store'])
            ->name('objects.media.store');
        Route::get('/media/{media}/edit', [AdminObjectMediaController::class, 'edit'])
            ->name('media.edit');
        Route::put('/media/{media}', [AdminObjectMediaController::class, 'update'])
            ->name('media.update');
        Route::delete('/media/{media}', [AdminObjectMediaController::class, 'destroy'])
            ->name('media.destroy');

        Route::get('/directories/{resource}', [AdminDirectoryController::class, 'index'])
            ->name('directories.index');
        Route::get('/directories/{resource}/create', [AdminDirectoryController::class, 'create'])
            ->name('directories.create');
        Route::post('/directories/{resource}', [AdminDirectoryController::class, 'store'])
            ->name('directories.store');
        Route::get('/directories/{resource}/{id}/edit', [AdminDirectoryController::class, 'edit'])
            ->whereNumber('id')
            ->name('directories.edit');
        Route::put('/directories/{resource}/{id}', [AdminDirectoryController::class, 'update'])
            ->whereNumber('id')
            ->name('directories.update');
        Route::delete('/directories/{resource}/{id}', [AdminDirectoryController::class, 'destroy'])
            ->whereNumber('id')
            ->name('directories.destroy');
    });
