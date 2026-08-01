<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageObject;
use App\Models\PointOfInterest;
use App\Services\MapViewportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MapObjectBySlugController extends Controller
{
    public function __invoke(
        Request $request,
        MapViewportService $map,
        string $slug
    ): Response {
        $objectId = PilgrimageObject::query()
            ->published()
            ->where('slug', $slug)
            ->value('id');

        abort_unless($objectId, 404, 'Объект не найден или не опубликован.');

        $data = $map->objectDetail((int) $objectId);
        $data['nearby_points'] = PointOfInterest::query()
            ->published()
            ->where('pilgrimage_object_id', (int) $objectId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->ordered()
            ->limit(100)
            ->get([
                'id',
                'pilgrimage_object_id',
                'category',
                'name',
                'description',
                'address',
                'latitude',
                'longitude',
                'phone',
                'website',
                'schedule_text',
                'sort_order',
            ])
            ->map(fn (PointOfInterest $point): array => [
                'id' => $point->id,
                'category' => $point->category,
                'category_label' => $point->category_label,
                'icon' => $point->category_icon,
                'marker_color' => $point->marker_color,
                'name' => $point->name,
                'description' => $point->description,
                'address' => $point->address,
                'latitude' => (float) $point->latitude,
                'longitude' => (float) $point->longitude,
                'phone' => $point->phone,
                'website' => $point->website,
                'schedule' => $point->schedule_text,
            ])
            ->values()
            ->all();

        $payload = ['data' => $data];
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        $etag = '"'.sha1((string) $json).'"';
        $headers = [
            'Cache-Control' => 'public, max-age=60, stale-while-revalidate=120',
            'ETag' => $etag,
            'Vary' => 'Accept-Encoding',
        ];

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response($json, 200, $headers + [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
