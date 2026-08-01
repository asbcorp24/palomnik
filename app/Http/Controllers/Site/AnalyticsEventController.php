<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsEventController extends Controller
{
    public function bookingStarted(
        Request $request,
        Trip $trip,
        AnalyticsService $analytics
    ): JsonResponse {
        $trip->loadMissing('pilgrimageRoute:id,title');

        $analytics->track($request, 'booking_form_started', $trip, [
            'trip_id' => $trip->id,
            'route_id' => $trip->pilgrimage_route_id,
            'trip_status' => $trip->status,
        ]);

        return response()->json(['ok' => true]);
    }
}
