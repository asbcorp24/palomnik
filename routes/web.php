<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DirectoryController as AdminDirectoryController;
use App\Http\Controllers\Admin\ModerationController as AdminModerationController;
use App\Http\Controllers\Admin\ObjectMediaController as AdminObjectMediaController;
use App\Http\Controllers\Admin\PilgrimageObjectController as AdminPilgrimageObjectController;
use App\Http\Controllers\Admin\PlatformModuleController as AdminPlatformModuleController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Site\HomeController as SiteHomeController;
use App\Http\Controllers\Site\MapController as SiteMapController;
use App\Http\Controllers\Site\ObjectController as SiteObjectController;
use App\Http\Controllers\Site\RouteController as SiteRouteController;
use Illuminate\Support\Facades\Route;

Route::get('/', SiteHomeController::class)->name('home');
Route::get('/map', SiteMapController::class)->name('map');
Route::get('/objects', [SiteObjectController::class, 'index'])->name('objects.index');
Route::get('/objects/{object:slug}', [SiteObjectController::class, 'show'])->name('objects.show');
Route::get('/routes', [SiteRouteController::class, 'index'])->name('routes.index');
Route::get('/routes/{pilgrimageRoute:slug}', [SiteRouteController::class, 'show'])->name('routes.show');

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

        Route::get('/modules/{resource}', [AdminPlatformModuleController::class, 'index'])
            ->name('modules.index');
        Route::get('/modules/{resource}/create', [AdminPlatformModuleController::class, 'create'])
            ->name('modules.create');
        Route::post('/modules/{resource}', [AdminPlatformModuleController::class, 'store'])
            ->name('modules.store');
        Route::get('/modules/{resource}/{id}/edit', [AdminPlatformModuleController::class, 'edit'])
            ->whereNumber('id')
            ->name('modules.edit');
        Route::put('/modules/{resource}/{id}', [AdminPlatformModuleController::class, 'update'])
            ->whereNumber('id')
            ->name('modules.update');
        Route::delete('/modules/{resource}/{id}', [AdminPlatformModuleController::class, 'destroy'])
            ->whereNumber('id')
            ->name('modules.destroy');

        Route::get('/moderation/{resource}', [AdminModerationController::class, 'index'])
            ->name('moderation.index');
        Route::put('/moderation/{resource}/{id}', [AdminModerationController::class, 'update'])
            ->whereNumber('id')
            ->name('moderation.update');
        Route::delete('/moderation/{resource}/{id}', [AdminModerationController::class, 'destroy'])
            ->whereNumber('id')
            ->name('moderation.destroy');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');

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
