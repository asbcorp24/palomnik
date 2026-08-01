<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageRoute;
use App\Models\Trip;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsEventController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', Rule::in(['booking_form_started'])],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
            'route_id' => ['nullable', 'integer', 'exists:pilgrimage_routes,id'],
        ]);

        $entity = null;
        if (! empty($data['trip_id'])) {
            $entity = Trip::query()->find($data['trip_id']);
        } elseif (! empty($data['route_id'])) {
            $entity = PilgrimageRoute::query()->find($data['route_id']);
        }

        $analytics->track($request, $data['event'], $entity, [
            'trip_id' => $data['trip_id'] ?? null,
            'route_id' => $data['route_id'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }
}
