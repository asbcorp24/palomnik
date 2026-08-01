<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PointOfInterest;
use App\Services\MapViewportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MapViewportController extends Controller
{
    public function objects(Request $request, MapViewportService $map): Response
    {
        $filters = $this->viewportFilters($request);
        $payload = $map->objects($filters);

        return $this->cacheableJson($request, $payload);
    }

    public function pointsOfInterest(Request $request, MapViewportService $map): Response
    {
        $filters = $this->viewportFilters($request, true);
        $payload = $map->pointsOfInterest($filters);

        return $this->cacheableJson($request, $payload);
    }

    public function object(Request $request, MapViewportService $map, int $objectId): Response
    {
        $payload = ['data' => $map->objectDetail($objectId)];

        return $this->cacheableJson($request, $payload, 300);
    }

    public function pointOfInterest(Request $request, MapViewportService $map, int $pointId): Response
    {
        $payload = ['data' => $map->pointOfInterestDetail($pointId)];

        return $this->cacheableJson($request, $payload, 300);
    }

    private function viewportFilters(Request $request, bool $withCategories = false): array
    {
        $rules = [
            'min_lat' => ['required', 'numeric', 'between:-90,90'],
            'max_lat' => ['required', 'numeric', 'between:-90,90'],
            'min_lng' => ['required', 'numeric', 'between:-180,180'],
            'max_lng' => ['required', 'numeric', 'between:-180,180'],
            'zoom' => ['required', 'numeric', 'between:0,20'],
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'vicariate' => ['nullable', 'string', 'max:255'],
            'deanery' => ['nullable', 'string', 'max:255'],
            'sanctity' => ['nullable', 'string', 'max:255'],
        ];

        if ($withCategories) {
            $rules['categories'] = ['nullable', 'array', 'max:10'];
            $rules['categories.*'] = ['required', Rule::in(array_keys(PointOfInterest::CATEGORIES))];
        }

        $filters = $request->validate($rules);

        if ((float) $filters['min_lat'] >= (float) $filters['max_lat']) {
            throw ValidationException::withMessages([
                'max_lat' => 'Северная граница должна быть больше южной.',
            ]);
        }

        if ((float) $filters['min_lng'] >= (float) $filters['max_lng']) {
            throw ValidationException::withMessages([
                'max_lng' => 'Восточная граница должна быть больше западной.',
            ]);
        }

        if ($withCategories) {
            $categories = $filters['categories'] ?? ['__none__'];
            $filters['categories'] = array_values(array_unique($categories));
            sort($filters['categories']);
        }

        return $filters;
    }

    private function cacheableJson(Request $request, array $payload, int $browserTtl = 60): Response
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        $etag = '"'.sha1((string) $json).'"';
        $headers = [
            'Cache-Control' => 'public, max-age='.$browserTtl.', stale-while-revalidate='.($browserTtl * 2),
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
