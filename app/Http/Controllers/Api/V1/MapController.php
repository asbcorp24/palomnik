<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class MapController extends Controller
{
    public function style(): JsonResponse
    {
        $vectorTiles = config('palomnik.maps.openmaptiles_tiles');
        $rasterTiles = config('palomnik.maps.raster_tiles');
        $attribution = config('palomnik.maps.attribution', '© OpenStreetMap contributors');

        if ($vectorTiles) {
            return response()->json($this->vectorStyle($vectorTiles, $attribution));
        }

        return response()->json([
            'version' => 8,
            'name' => 'Moscow Pilgrim OSM fallback',
            'sources' => [
                'osm' => [
                    'type' => 'raster',
                    'tiles' => [$rasterTiles ?: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                    'tileSize' => 256,
                    'attribution' => $attribution,
                    'maxzoom' => 19,
                ],
            ],
            'layers' => [
                ['id' => 'background', 'type' => 'background', 'paint' => ['background-color' => '#f4f0e7']],
                ['id' => 'osm', 'type' => 'raster', 'source' => 'osm'],
            ],
        ]);
    }

    public function route(Request $request): JsonResponse
    {
        $data = $request->validate([
            'locations' => ['required', 'array', 'min:2', 'max:25'],
            'locations.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'mode' => ['required', Rule::in(['pedestrian', 'auto', 'bicycle', 'bus', 'multimodal'])],
            'optimize' => ['nullable', 'boolean'],
        ]);

        $baseUrl = rtrim((string) config('palomnik.maps.valhalla_url'), '/');
        if ($baseUrl === '') {
            return response()->json([
                'message' => 'Сервис маршрутизации Valhalla не настроен.',
            ], 503);
        }

        $payload = [
            'locations' => collect($data['locations'])->map(fn (array $location) => [
                'lat' => (float) $location['latitude'],
                'lon' => (float) $location['longitude'],
                'type' => 'break',
            ])->values()->all(),
            'costing' => $data['mode'],
            'units' => 'kilometers',
            'language' => 'ru-RU',
            'format' => 'osrm',
            'shape_format' => 'geojson',
            'directions_options' => ['units' => 'kilometers'],
        ];

        $action = ! empty($data['optimize']) && count($payload['locations']) >= 4
            ? 'optimized_route'
            : 'route';

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('palomnik.maps.valhalla_timeout', 20))
                ->retry(2, 250)
                ->post($baseUrl.'/'.$action, $payload);
        } catch (ConnectionException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Сервис маршрутизации временно недоступен.',
            ], 503);
        }

        if (! $response->successful()) {
            return response()->json([
                'message' => data_get($response->json(), 'error')
                    ?: data_get($response->json(), 'error_code')
                    ?: 'Не удалось построить маршрут.',
            ], $response->status() >= 400 && $response->status() < 500 ? 422 : 503);
        }

        $route = data_get($response->json(), 'routes.0');
        if (! is_array($route) || ! is_array($route['geometry'] ?? null)) {
            return response()->json(['message' => 'Маршрут не найден.'], 422);
        }

        return response()->json([
            'data' => [
                'geometry' => $route['geometry'],
                'distance_meters' => (float) ($route['distance'] ?? 0),
                'duration_seconds' => (float) ($route['duration'] ?? 0),
                'weight' => $route['weight'] ?? null,
                'legs' => $route['legs'] ?? [],
                'waypoints' => $response->json('waypoints', []),
                'mode' => $data['mode'],
                'optimized' => $action === 'optimized_route',
            ],
        ]);
    }

    private function vectorStyle(string $tileUrl, string $attribution): array
    {
        $glyphs = config('palomnik.maps.glyphs_url');

        $style = [
            'version' => 8,
            'name' => 'Moscow Pilgrim OpenMapTiles',
            'sources' => [
                'openmaptiles' => [
                    'type' => 'vector',
                    'tiles' => [$tileUrl],
                    'minzoom' => 0,
                    'maxzoom' => 14,
                    'attribution' => $attribution,
                ],
            ],
            'layers' => [
                ['id' => 'background', 'type' => 'background', 'paint' => ['background-color' => '#f6f1e7']],
                ['id' => 'landcover', 'type' => 'fill', 'source' => 'openmaptiles', 'source-layer' => 'landcover', 'paint' => ['fill-color' => '#e7eadc', 'fill-opacity' => 0.75]],
                ['id' => 'landuse', 'type' => 'fill', 'source' => 'openmaptiles', 'source-layer' => 'landuse', 'paint' => ['fill-color' => '#eee7d8', 'fill-opacity' => 0.55]],
                ['id' => 'park', 'type' => 'fill', 'source' => 'openmaptiles', 'source-layer' => 'park', 'paint' => ['fill-color' => '#dce8d3', 'fill-opacity' => 0.9]],
                ['id' => 'water', 'type' => 'fill', 'source' => 'openmaptiles', 'source-layer' => 'water', 'paint' => ['fill-color' => '#b9d7df']],
                ['id' => 'waterway', 'type' => 'line', 'source' => 'openmaptiles', 'source-layer' => 'waterway', 'paint' => ['line-color' => '#a8cdd7', 'line-width' => 1.2]],
                ['id' => 'boundary', 'type' => 'line', 'source' => 'openmaptiles', 'source-layer' => 'boundary', 'paint' => ['line-color' => '#aa9a85', 'line-width' => 1, 'line-dasharray' => [3, 2]]],
                ['id' => 'roads-casing', 'type' => 'line', 'source' => 'openmaptiles', 'source-layer' => 'transportation', 'paint' => ['line-color' => '#d7cfc0', 'line-width' => ['interpolate', ['linear'], ['zoom'], 7, 0.5, 16, 8]]],
                ['id' => 'roads', 'type' => 'line', 'source' => 'openmaptiles', 'source-layer' => 'transportation', 'paint' => ['line-color' => '#fffdf8', 'line-width' => ['interpolate', ['linear'], ['zoom'], 7, 0.25, 16, 5.5]]],
                ['id' => 'buildings', 'type' => 'fill', 'source' => 'openmaptiles', 'source-layer' => 'building', 'minzoom' => 13, 'paint' => ['fill-color' => '#d8c9b4', 'fill-outline-color' => '#c5b49d', 'fill-opacity' => 0.78]],
            ],
        ];

        if ($glyphs) {
            $style['glyphs'] = $glyphs;
            $style['layers'][] = [
                'id' => 'place-labels',
                'type' => 'symbol',
                'source' => 'openmaptiles',
                'source-layer' => 'place',
                'layout' => [
                    'text-field' => ['coalesce', ['get', 'name:ru'], ['get', 'name']],
                    'text-font' => ['Open Sans Regular'],
                    'text-size' => ['interpolate', ['linear'], ['zoom'], 4, 10, 12, 15],
                    'text-allow-overlap' => false,
                ],
                'paint' => [
                    'text-color' => '#50483f',
                    'text-halo-color' => '#fffdf9',
                    'text-halo-width' => 1.2,
                ],
            ];
        }

        return $style;
    }
}
