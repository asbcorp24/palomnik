<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CalendarEventController as AdminCalendarEventController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DirectoryController as AdminDirectoryController;
use App\Http\Controllers\Admin\ModerationController as AdminModerationController;
use App\Http\Controllers\Admin\ObjectMediaController as AdminObjectMediaController;
use App\Http\Controllers\Admin\PilgrimageObjectController as AdminPilgrimageObjectController;
use App\Http\Controllers\Admin\PlatformModuleController as AdminPlatformModuleController;
use App\Http\Controllers\Admin\RepresentativeController as AdminRepresentativeController;
use App\Http\Controllers\Admin\SafetyController as AdminSafetyController;
use App\Http\Controllers\Admin\ServiceReviewController as AdminServiceReviewController;
use App\Http\Controllers\Admin\TogetherController as AdminTogetherController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Service\DashboardController as ServiceDashboardController;
use App\Http\Controllers\Service\ObjectController as ServiceObjectController;
use App\Http\Controllers\Service\TicketScannerController as ServiceTicketScannerController;
use App\Http\Controllers\Site\AuthController as SiteAuthController;
use App\Http\Controllers\Site\BlogPostController as SiteBlogPostController;
use App\Http\Controllers\Site\BookingController as SiteBookingController;
use App\Http\Controllers\Site\CalendarController as SiteCalendarController;
use App\Http\Controllers\Site\CommunityController as SiteCommunityController;
use App\Http\Controllers\Site\FavoriteController as SiteFavoriteController;
use App\Http\Controllers\Site\HomeController as SiteHomeController;
use App\Http\Controllers\Site\MapController as SiteMapController;
use App\Http\Controllers\Site\NotificationController as SiteNotificationController;
use App\Http\Controllers\Site\ObjectController as SiteObjectController;
use App\Http\Controllers\Site\ProfileController as SiteProfileController;
use App\Http\Controllers\Site\ReviewController as SiteReviewController;
use App\Http\Controllers\Site\RouteController as SiteRouteController;
use App\Http\Controllers\Site\RoutePlanController as SiteRoutePlanController;
use App\Http\Controllers\Site\SafetyController as SiteSafetyController;
use App\Http\Controllers\Site\TicketController as SiteTicketController;
use App\Http\Controllers\Site\TogetherController as SiteTogetherController;
use App\Http\Controllers\Site\TogetherMessageController as SiteTogetherMessageController;
use App\Http\Controllers\Site\UserMediaController as SiteUserMediaController;
use App\Http\Controllers\Site\VisitController as SiteVisitController;
use Illuminate\Support\Facades\Route;

Route::get('/', SiteHomeController::class)->name('home');
Route::view('/offline', 'site.offline')->name('offline');
Route::view('/privacy', 'site.legal.privacy')->name('privacy');
Route::view('/terms', 'site.legal.terms')->name('terms');
Route::get('/map', SiteMapController::class)->name('map');
Route::get('/objects', [SiteObjectController::class, 'index'])->name('objects.index');
Route::get('/objects/{object:slug}', [SiteObjectController::class, 'show'])->name('objects.show');
Route::get('/routes', [SiteRouteController::class, 'index'])->name('routes.index');
Route::get('/routes/{pilgrimageRoute:slug}', [SiteRouteController::class, 'show'])->name('routes.show');

Route::get('/calendar', [SiteCalendarController::class, 'index'])->name('calendar.index');
Route::get('/calendar/{calendarEvent:slug}/ics', [SiteCalendarController::class, 'ics'])->name('calendar.ics');
Route::get('/calendar/{calendarEvent:slug}', [SiteCalendarController::class, 'show'])->name('calendar.show');

Route::get('/community', [SiteCommunityController::class, 'index'])->name('community.index');
Route::get('/community/together', [SiteTogetherController::class, 'index'])->name('together.index');
Route::get('/community/{post:slug}', [SiteCommunityController::class, 'show'])->name('community.show');
Route::redirect('/together', '/community/together', 301);

Route::middleware('guest')->group(function () {
    Route::get('/login', [SiteAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [SiteAuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    Route::get('/register', [SiteAuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [SiteAuthController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SiteAuthController::class, 'logout'])->name('logout');

    Route::get('/notifications', [SiteNotificationController::class, 'index'])->name('notifications.index');
    Route::put('/notifications/{notification}/read', [SiteNotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [SiteNotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/profile', [SiteProfileController::class, 'dashboard'])->name('profile.dashboard');
    Route::get('/profile/settings', [SiteProfileController::class, 'settings'])->name('profile.settings');
    Route::put('/profile/settings', [SiteProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/favorites', [SiteProfileController::class, 'favorites'])->name('profile.favorites');
    Route::get('/profile/bookings', [SiteProfileController::class, 'bookings'])->name('profile.bookings');
    Route::get('/profile/achievements', [SiteProfileController::class, 'achievements'])->name('profile.achievements');
    Route::get('/profile/activity', [SiteProfileController::class, 'activity'])->name('profile.activity');
    Route::get('/profile/blocked-users', [SiteProfileController::class, 'blockedUsers'])->name('profile.blocked-users');

    Route::get('/bookings/{booking}/ticket', [SiteTicketController::class, 'show'])->name('tickets.show');
    Route::get('/bookings/{booking}/calendar.ics', [SiteTicketController::class, 'ics'])->name('tickets.ics');

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

    Route::get('/community/together/my', [SiteTogetherController::class, 'my'])->name('together.my');
    Route::get('/community/together/create', [SiteTogetherController::class, 'create'])->name('together.create');
    Route::post('/community/together', [SiteTogetherController::class, 'store'])->name('together.store');
    Route::get('/community/together/{jointPilgrimage}/edit', [SiteTogetherController::class, 'edit'])->name('together.edit');
    Route::put('/community/together/{jointPilgrimage}', [SiteTogetherController::class, 'update'])->name('together.update');
    Route::delete('/community/together/{jointPilgrimage}', [SiteTogetherController::class, 'destroy'])->name('together.destroy');
    Route::post('/community/together/{jointPilgrimage}/join', [SiteTogetherController::class, 'join'])->name('together.join');
    Route::delete('/community/together/{jointPilgrimage}/leave', [SiteTogetherController::class, 'leave'])->name('together.leave');
    Route::put('/community/together/{jointPilgrimage}/members/{member}', [SiteTogetherController::class, 'updateMember'])->name('together.members.update');
    Route::get('/community/together/{jointPilgrimage}/messages-feed', [SiteTogetherMessageController::class, 'index'])->name('together.messages.index');
    Route::post('/community/together/{jointPilgrimage}/messages', [SiteTogetherController::class, 'storeMessage'])->name('together.messages.store');

    Route::post('/safety/reports', [SiteSafetyController::class, 'report'])->name('safety.reports.store');
    Route::post('/safety/blocks/{user}', [SiteSafetyController::class, 'block'])->name('safety.blocks.store');
    Route::delete('/safety/blocks/{user}', [SiteSafetyController::class, 'unblock'])->name('safety.blocks.destroy');

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

Route::get('/community/together/{jointPilgrimage:slug}', [SiteTogetherController::class, 'show'])->name('together.show');

Route::prefix('service')
    ->name('service.')
    ->middleware(['auth', 'service'])
    ->group(function () {
        Route::get('/', ServiceDashboardController::class)->name('dashboard');
        Route::get('/objects', [ServiceObjectController::class, 'index'])->name('objects.index');
        Route::get('/objects/{object}/edit', [ServiceObjectController::class, 'edit'])->name('objects.edit');
        Route::put('/objects/{object}', [ServiceObjectController::class, 'update'])->name('objects.update');
        Route::post('/objects/{object}/media', [ServiceObjectController::class, 'storeMedia'])->name('objects.media.store');

        Route::get('/tickets/scanner', [ServiceTicketScannerController::class, 'index'])->name('tickets.scanner');
        Route::get('/tickets/lookup', [ServiceTicketScannerController::class, 'lookup'])->middleware('throttle:120,1')->name('tickets.lookup');
        Route::post('/tickets/check-in', [ServiceTicketScannerController::class, 'checkIn'])->middleware('throttle:60,1')->name('tickets.check-in');
    });

Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1')->name('admin.login.submit');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin'])
    ->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::resource('calendar', AdminCalendarEventController::class)
            ->parameters(['calendar' => 'calendarEvent']);

        Route::resource('objects', AdminPilgrimageObjectController::class)->parameters(['objects' => 'object']);
        Route::post('/objects/{object}/media', [AdminObjectMediaController::class, 'store'])->name('objects.media.store');
        Route::get('/media/{media}/edit', [AdminObjectMediaController::class, 'edit'])->name('media.edit');
        Route::put('/media/{media}', [AdminObjectMediaController::class, 'update'])->name('media.update');
        Route::delete('/media/{media}', [AdminObjectMediaController::class, 'destroy'])->name('media.destroy');

        Route::get('/representatives', [AdminRepresentativeController::class, 'index'])->name('representatives.index');
        Route::post('/representatives', [AdminRepresentativeController::class, 'store'])->name('representatives.store');
        Route::put('/representatives/{representative}', [AdminRepresentativeController::class, 'update'])->name('representatives.update');
        Route::delete('/representatives/{representative}', [AdminRepresentativeController::class, 'destroy'])->name('representatives.destroy');

        Route::get('/service-review', [AdminServiceReviewController::class, 'index'])->name('service-review.index');
        Route::put('/service-review/requests/{updateRequest}', [AdminServiceReviewController::class, 'updateRequest'])->name('service-review.requests.update');
        Route::put('/service-review/media/{submission}', [AdminServiceReviewController::class, 'media'])->name('service-review.media.update');

        Route::get('/safety', [AdminSafetyController::class, 'index'])->name('safety.index');
        Route::put('/safety/{report}', [AdminSafetyController::class, 'update'])->name('safety.update');
        Route::put('/users/{user}/verified-organizer', [AdminSafetyController::class, 'verifyOrganizer'])->name('users.verify-organizer');

        Route::get('/together', [AdminTogetherController::class, 'index'])->name('together.index');
        Route::put('/together/{jointPilgrimage}', [AdminTogetherController::class, 'update'])->name('together.update');
        Route::delete('/together/{jointPilgrimage}', [AdminTogetherController::class, 'destroy'])->name('together.destroy');

        Route::get('/modules/{resource}', [AdminPlatformModuleController::class, 'index'])->name('modules.index');
        Route::get('/modules/{resource}/create', [AdminPlatformModuleController::class, 'create'])->name('modules.create');
        Route::post('/modules/{resource}', [AdminPlatformModuleController::class, 'store'])->name('modules.store');
        Route::get('/modules/{resource}/{id}/edit', [AdminPlatformModuleController::class, 'edit'])->whereNumber('id')->name('modules.edit');
        Route::put('/modules/{resource}/{id}', [AdminPlatformModuleController::class, 'update'])->whereNumber('id')->name('modules.update');
        Route::delete('/modules/{resource}/{id}', [AdminPlatformModuleController::class, 'destroy'])->whereNumber('id')->name('modules.destroy');

        Route::get('/moderation/{resource}', [AdminModerationController::class, 'index'])->name('moderation.index');
        Route::put('/moderation/{resource}/{id}', [AdminModerationController::class, 'update'])->whereNumber('id')->name('moderation.update');
        Route::delete('/moderation/{resource}/{id}', [AdminModerationController::class, 'destroy'])->whereNumber('id')->name('moderation.destroy');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');

        Route::get('/directories/{resource}', [AdminDirectoryController::class, 'index'])->name('directories.index');
        Route::get('/directories/{resource}/create', [AdminDirectoryController::class, 'create'])->name('directories.create');
        Route::post('/directories/{resource}', [AdminDirectoryController::class, 'store'])->name('directories.store');
        Route::get('/directories/{resource}/{id}/edit', [AdminDirectoryController::class, 'edit'])->whereNumber('id')->name('directories.edit');
        Route::put('/directories/{resource}/{id}', [AdminDirectoryController::class, 'update'])->whereNumber('id')->name('directories.update');
        Route::delete('/directories/{resource}/{id}', [AdminDirectoryController::class, 'destroy'])->whereNumber('id')->name('directories.destroy');
    });
