<?php

use App\Http\Controllers\Site\AnalyticsEventController;
use Illuminate\Support\Facades\Route;

Route::post('/analytics/booking-started/{trip}', [AnalyticsEventController::class, 'bookingStarted'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('analytics.booking-started');
