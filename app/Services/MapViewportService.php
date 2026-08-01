<?php

namespace App\Services;

use App\Models\PilgrimageObject;
use App\Models\PointOfInterest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MapViewportService
{
    public const OBJECT_DETAIL_TTL_SECONDS = 300;
    public const VIEWPORT_TTL_SECONDS = 90;
    public const SERVER_CLUSTER_MAX_ZOOM = 9.99;
    public const POINTS_OF_INTEREST_MIN_ZOOM = 12.0;

    public function objects(array $filters): array
    {
        $zoom = (float) $filters['zoom'];
        $bounds = $this->normalizedBounds($filters, $zoom);
        $mode = $zoom <= self::SERVER_CLUSTER_MAX_ZOOM ? 'clusters' : 'points';
        $cacheKey = $this->cacheKey('objects-'.$mode, $filters, $bounds);

        return Cache::remember(
            $cacheKey,
            now()->addSeconds((int) config('palomnik.maps.viewport_cache_ttl', self::VIEWPORT_TTL_SECONDS)),
            function () use ($filters, $bounds, $zoom, $mode): array {
                $query = $this->objectQuery($filters, $bounds);

                return $mode === 'clusters'
                    ? $this->serverClusters($query, $bounds, $zoom)
                    : $this->objectMarkers($query, $bounds, $zoom);
            }
        );
    }

    public function pointsOfInterest(array $filters): array
    {
        $zoom = (float) $filters['zoom'];
        $bounds = $this->normalizedBounds($filters, $zoom);

        if ($zoom < self::POINTS_OF_INTEREST_MIN_ZOOM) {
            return [
                'type' => 'FeatureCollection',
                'features' => [],
                'meta' => [
                    'mode' => 'hidden',
                    'min_zoom' => self::POINTS_OF_INTEREST_MIN_ZOOM,
                    'returned' => 0,
                    'truncated' => false,
                    'bounds' => $bounds,
                ],
            ];
        }

        $cacheKey = $this->cacheKey('poi-points', $filters, $bounds);

        return Cache::remember(
            $cacheKey,
            now()->addSeconds((int) config('palomnik.maps.viewport_cache_ttl', self::VIEWPORT_TTL_SECONDS)),
            function () use ($filters, $bounds): array {
                $limit = max(100, min(2000, (int) config('palomnik.maps.viewport_poi_limit', 1000)));

                $query = PointOfInterest::query()
                    ->published()
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereBetween('latitude', [$bounds['min_lat'], $bounds['max_lat']])
                    ->whereBetween('longitude', [$bounds['min_lng'], $bounds['max_lng']])
                    ->whereHas('pilgrimageObject', fn (Builder $query) => $query->published())
                    ->when($filters['categories'] ?? [], function (Builder $query, array $categories): void {
                        $query->whereIn('category', $categories);
                    })
                    ->orderBy('id')
                    ->limit($limit + 1)
                    ->get([
                        'id',
                        'pilgrimage_object_id',
                        'category',
                        'latitude',
                        'longitude',
                    ]);

                $truncated = $query->count() > $limit;
                $items = $query->take($limit)->values();

                return [
                    'type' => 'FeatureCollection',
                    'features' => $items->map(fn (PointOfInterest $point): array => [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [(float) $point->longitude, (float) $point->latitude],
                        ],
                        'properties' => [
                            'id' => (string) $point->id,
                            'category' => $point->category,
                            'marker_color' => $point->marker_color,
                        ],
                    ])->all(),
                    'meta' => [
                        'mode' => 'points',
                        'min_zoom' => self::POINTS_OF_INTEREST_MIN_ZOOM,
                        'returned' => $items->count(),
                        'truncated' => $truncated,
                        'bounds' => $bounds,
                    ],
                ];
            }
        );
    }

    public function objectDetail(int $objectId): array
    {
        return Cache::remember(
            'map:object-detail:'.$objectId,
            now()->addSeconds((int) config('palomnik.maps.detail_cache_ttl', self::OBJECT_DETAIL_TTL_SECONDS)),
            function () use ($objectId): array {
                $object = PilgrimageObject::query()
                    ->published()
                    ->with([
                        'objectType:id,name,slug,marker_color',
                        'vicariate:id,name',
                        'deanery:id,name',
                        'coverMedia:id,pilgrimage_object_id,path,external_url,title,is_cover,sort_order',
                        'sanctities' => fn ($query) => $query
                            ->where('slug', '<>', 'holy-spring')
                            ->orderBy('name')
                            ->limit(20),
                    ])
                    ->findOrFail($objectId);

                return [
                    'id' => $object->id,
                    'name' => $object->name,
                    'type' => $object->objectType?->name ?: 'Паломнический объект',
                    'type_slug' => $object->objectType?->slug,
                    'marker_color' => $object->objectType?->marker_color ?: '#b08a3e',
                    'vicariate' => $object->vicariate?->name,
                    'deanery' => $object->deanery?->name,
                    'address' => $object->address,
                    'latitude' => (float) $object->latitude,
                    'longitude' => (float) $object->longitude,
                    'cover' => $object->coverMedia?->url,
                    'short_description' => $object->short_description,
                    'schedule' => $object->schedule_text,
                    'phone' => $object->phone,
                    'website' => $object->website,
                    'sanctities' => $object->sanctities->pluck('name')->values()->all(),
                    'information_verified_at' => $object->information_verified_at?->toIso8601String(),
                    'url' => route('objects.show', $object),
                ];
            }
        );
    }

    public function pointOfInterestDetail(int $pointId): array
    {
        return Cache::remember(
            'map:poi-detail:'.$pointId,
            now()->addSeconds((int) config('palomnik.maps.detail_cache_ttl', self::OBJECT_DETAIL_TTL_SECONDS)),
            function () use ($pointId): array {
                $point = PointOfInterest::query()
                    ->published()
                    ->with(['pilgrimageObject' => fn ($query) => $query->published()])
                    ->whereHas('pilgrimageObject', fn (Builder $query) => $query->published())
                    ->findOrFail($pointId);

                return [
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
                    'base_object_id' => $point->pilgrimage_object_id,
                    'base_object_name' => $point->pilgrimageObject?->name,
                    'base_object_url' => $point->pilgrimageObject
                        ? route('objects.show', $point->pilgrimageObject)
                        : null,
                ];
            }
        );
    }

    private function objectQuery(array $filters, array $bounds): Builder
    {
        return PilgrimageObject::query()
            ->published()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$bounds['min_lat'], $bounds['max_lat']])
            ->whereBetween('longitude', [$bounds['min_lng'], $bounds['max_lng']])
            ->search($filters['q'] ?? null)
            ->when($filters['type'] ?? null, function (Builder $query, string $slug): void {
                $query->whereHas('objectType', fn (Builder $query) => $query->visible()->where('slug', $slug));
            })
            ->when($filters['vicariate'] ?? null, function (Builder $query, string $slug): void {
                $query->whereHas('vicariate', fn (Builder $query) => $query->where('slug', $slug));
            })
            ->when($filters['deanery'] ?? null, function (Builder $query, string $slug): void {
                $query->whereHas('deanery', fn (Builder $query) => $query->where('slug', $slug));
            })
            ->when($filters['sanctity'] ?? null, function (Builder $query, string $slug): void {
                $query->whereHas('sanctities', fn (Builder $query) => $query->where('slug', $slug));
            });
    }

    private function serverClusters(Builder $query, array $bounds, float $zoom): array
    {
        $cellSize = $this->clusterCellSize($zoom);
        $cellSql = rtrim(rtrim(number_format($cellSize, 6, '.', ''), '0'), '.');
        $latExpression = 'FLOOR(latitude / '.$cellSql.')';
        $lngExpression = 'FLOOR(longitude / '.$cellSql.')';
        $limit = max(100, min(1000, (int) config('palomnik.maps.viewport_cluster_limit', 600)));

        $rows = (clone $query)
            ->selectRaw($latExpression.' AS lat_cell')
            ->selectRaw($lngExpression.' AS lng_cell')
            ->selectRaw('COUNT(*) AS point_count')
            ->selectRaw('AVG(latitude) AS latitude')
            ->selectRaw('AVG(longitude) AS longitude')
            ->groupBy(DB::raw($latExpression), DB::raw($lngExpression))
            ->orderByDesc('point_count')
            ->limit($limit + 1)
            ->get();

        $truncated = $rows->count() > $limit;
        $items = $rows->take($limit)->values();

        return [
            'type' => 'FeatureCollection',
            'features' => $items->map(fn ($row): array => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $row->longitude, (float) $row->latitude],
                ],
                'properties' => [
                    'kind' => 'server_cluster',
                    'point_count' => (int) $row->point_count,
                    'point_count_abbreviated' => $this->abbreviatedCount((int) $row->point_count),
                    'target_zoom' => min(19, (int) floor($zoom) + 2),
                ],
            ])->all(),
            'meta' => [
                'mode' => 'server_clusters',
                'returned' => $items->count(),
                'visible_objects' => (int) $items->sum('point_count'),
                'truncated' => $truncated,
                'bounds' => $bounds,
                'cell_size' => $cellSize,
                'switch_to_points_zoom' => self::SERVER_CLUSTER_MAX_ZOOM + 0.01,
            ],
        ];
    }

    private function objectMarkers(Builder $query, array $bounds, float $zoom): array
    {
        $limit = $zoom < 11
            ? 1000
            : ($zoom < 12 ? 1500 : max(1500, min(4000, (int) config('palomnik.maps.viewport_object_limit', 2500))));

        $rows = (clone $query)
            ->with('objectType:id,name,slug,marker_color')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get([
                'id',
                'object_type_id',
                'name',
                'address',
                'latitude',
                'longitude',
            ]);

        $truncated = $rows->count() > $limit;
        $items = $rows->take($limit)->values();

        return [
            'type' => 'FeatureCollection',
            'features' => $items->map(fn (PilgrimageObject $object): array => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $object->longitude, (float) $object->latitude],
                ],
                'properties' => [
                    'kind' => 'object',
                    'id' => (string) $object->id,
                    'name' => $object->name,
                    'type' => $object->objectType?->name ?: 'Паломнический объект',
                    'type_slug' => $object->objectType?->slug,
                    'address' => $object->address ?: '',
                    'marker_color' => $object->objectType?->marker_color ?: '#b08a3e',
                ],
            ])->all(),
            'meta' => [
                'mode' => 'points',
                'returned' => $items->count(),
                'visible_objects' => $truncated ? null : $items->count(),
                'truncated' => $truncated,
                'limit' => $limit,
                'bounds' => $bounds,
            ],
        ];
    }

    private function normalizedBounds(array $filters, float $zoom): array
    {
        $precision = $zoom < 8 ? 1 : ($zoom < 12 ? 2 : 3);
        $factor = 10 ** $precision;

        return [
            'min_lat' => floor(((float) $filters['min_lat']) * $factor) / $factor,
            'max_lat' => ceil(((float) $filters['max_lat']) * $factor) / $factor,
            'min_lng' => floor(((float) $filters['min_lng']) * $factor) / $factor,
            'max_lng' => ceil(((float) $filters['max_lng']) * $factor) / $factor,
        ];
    }

    private function clusterCellSize(float $zoom): float
    {
        if ($zoom < 6) {
            return 0.8;
        }
        if ($zoom < 7) {
            return 0.45;
        }
        if ($zoom < 8) {
            return 0.24;
        }
        if ($zoom < 9) {
            return 0.12;
        }

        return 0.06;
    }

    private function abbreviatedCount(int $count): string
    {
        if ($count >= 1000) {
            return number_format($count / 1000, 1, '.', '').'k';
        }

        return (string) $count;
    }

    private function cacheKey(string $scope, array $filters, array $bounds): string
    {
        $payload = [
            'scope' => $scope,
            'zoom' => round((float) $filters['zoom'], 2),
            'bounds' => $bounds,
            'q' => trim((string) ($filters['q'] ?? '')),
            'type' => $filters['type'] ?? null,
            'vicariate' => $filters['vicariate'] ?? null,
            'deanery' => $filters['deanery'] ?? null,
            'sanctity' => $filters['sanctity'] ?? null,
            'categories' => array_values($filters['categories'] ?? []),
        ];

        return 'map:viewport:'.hash(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
