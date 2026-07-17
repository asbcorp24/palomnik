<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DirectoryController as AdminDirectoryController;
use App\Http\Controllers\Admin\ModerationController as AdminModerationController;
use App\Http\Controllers\Admin\ObjectMediaController as AdminObjectMediaController;
use App\Http\Controllers\Admin\PilgrimageObjectController as AdminPilgrimageObjectController;
use App\Http\Controllers\Admin\PlatformModuleController as AdminPlatformModuleController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Site\AuthController as SiteAuthController;
use App\Http\Controllers\Site\BlogPostController as SiteBlogPostController;
use App\Http\Controllers\Site\BookingController as SiteBookingController;
use App\Http\Controllers\Site\CommunityController as SiteCommunityController;
use App\Http\Controllers\Site\FavoriteController as SiteFavoriteController;
use App\Http\Controllers\Site\HomeController as SiteHomeController;
use App\Http\Controllers\Site\MapController as SiteMapController;
use App\Http\Controllers\Site\ObjectController as SiteObjectController;
use App\Http\Controllers\Site\ProfileController as SiteProfileController;
use App\Http\Controllers\Site\ReviewController as SiteReviewController;
use App\Http\Controllers\Site\RouteController as SiteRouteController;
use App\Http\Controllers\Site\RoutePlanController as SiteRoutePlanController;
use App\Http\Controllers\Site\UserMediaController as SiteUserMediaController;
use App\Http\Controllers\Site\VisitController as SiteVisitController;
use Illuminate\Support\Facades\Route;

Route::get('/', SiteHomeController::class)->name('home');
Route::view('/offline', 'site.offline')->name('offline');
Route::get('/map', SiteMapController::class)->name('map');
Route::get('/objects', [SiteObjectController::class, 'index'])->name('objects.index');
Route::get('/objects/{object:slug}', [SiteObjectController::class, 'show'])->name('objects.show');
Route::get('/routes', [SiteRouteController::class, 'index'])->name('routes.index');
Route::get('/routes/{pilgrimageRoute:slug}', [SiteRouteController::class, 'show'])->name('routes.show');
Route::get('/community', [SiteCommunityController::class, 'index'])->name('community.index');
Route::get('/community/{post:slug}', [SiteCommunityController::class, 'show'])->name('community.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SiteAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [SiteAuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    Route::get('/register', [SiteAuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [SiteAuthController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SiteAuthController::class, 'logout'])->name('logout');

    Route::get('/profile', [SiteProfileController::class, 'dashboard'])->name('profile.dashboard');
    Route::get('/profile/settings', [SiteProfileController::class, 'settings'])->name('profile.settings');
    Route::put('/profile/settings', [SiteProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/favorites', [SiteProfileController::class, 'favorites'])->name('profile.favorites');
    Route::get('/profile/bookings', [SiteProfileController::class, 'bookings'])->name('profile.bookings');
    Route::get('/profile/achievements', [SiteProfileController::class, 'achievements'])->name('profile.achievements');
    Route::get('/profile/activity', [SiteProfileController::class, 'activity'])->name('profile.activity');

    Route::post('/favorites/lists', [SiteFavoriteController::class, 'storeList'])->name('favorites.lists.store');
    Route::delete('/favorites/lists/{favoriteList}', [SiteFavoriteController::class, 'destroyList'])->name('favorites.lists.destroy');
    Route::post('/favorites/objects/{object}', [SiteFavoriteController::class, 'addObject'])->name('favorites.objects.add');
    Route::delete('/favorites/lists/{favoriteList}/objects/{object}', [SiteFavoriteController::class, 'removeObject'])->name('favorites.objects.remove');

    Route::post('/objects/{object}/reviews', [SiteReviewController::class, 'store'])->name('reviews.store');
    Route::delete('/reviews/{review}', [SiteReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::post('/objects/{object}/visits', [SiteVisitController::class, 'store'])->name('visits.store');

    Route::post('/trips/{trip}/bookings', [SiteBookingController::class, 'store'])->name('bookings.store');
    Route::delete('/bookings/{booking}', [SiteBookingController::class, 'cancel'])->name('bookings.cancel');

    Route::get('/community/posts/create', [SiteBlogPostController::class, 'create'])->name('community.posts.create');
    Route::post('/community/posts', [SiteBlogPostController::class, 'store'])->name('community.posts.store');
    Route::get('/community/posts/{post}/edit', [SiteBlogPostController::class, 'edit'])->name('community.posts.edit');
    Route::put('/community/posts/{post}', [SiteBlogPostController::class, 'update'])->name('community.posts.update');
    Route::delete('/community/posts/{post}', [SiteBlogPostController::class, 'destroy'])->name('community.posts.destroy');
    Route::post('/community/media', [SiteUserMediaController::class, 'store'])->name('community.media.store');
    Route::delete('/community/media/{media}', [SiteUserMediaController::class, 'destroy'])->name('community.media.destroy');

    Route::resource('my-routes', SiteRoutePlanController::class)
        ->parameters(['my-routes' => 'plan'])
        ->names([
            'index' => 'route-plans.index',
            'create' => 'route-plans.create',
            'store' => 'route-plans.store',
            'show' => 'route-plans.show',
            'edit' => 'route-plans.edit',
            'update' => 'route-plans.update',
            'destroy' => 'route-plans.destroy',
        ]);
});

Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
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
