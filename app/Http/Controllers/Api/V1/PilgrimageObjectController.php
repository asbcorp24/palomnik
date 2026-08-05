<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PilgrimageObjectResource;
use App\Models\AnalyticsEvent;
use App\Models\PilgrimageObject;
use App\Services\AnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PilgrimageObjectController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'vicariate' => ['nullable', 'string', 'max:255'],
            'deanery' => ['nullable', 'string', 'max:255'],
            'sanctity' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:none,popular,reviews,name,newest'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = PilgrimageObject::query()
            ->published()
            ->with([
                'objectType',
                'vicariate',
                'deanery',
                'coverMedia',
                'sanctities',
                'parentObject.objectType',
                'publishedChildObjects.objectType',
            ])
            ->withCount([
                'publishedChildObjects',
                'reviews as published_reviews_count' => fn ($query) => $query->where('status', 'published'),
            ])
            ->withAvg(
                ['reviews as published_rating' => fn ($query) => $query->where('status', 'published')],
                'rating'
            )
            ->addSelect([
                'popularity_count' => AnalyticsEvent::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('entity_id', 'pilgrimage_objects.id')
                    ->where('event', 'object_view')
                    ->where('entity_type', 'PilgrimageObject'),
            ])
            ->search($validated['q'] ?? null);

        $query->typeOrParentOfType($validated['type'] ?? null);

        $query->when($validated['vicariate'] ?? null, function (Builder $query, string $slug) {
            $query->whereHas('vicariate', function (Builder $query) use ($slug) {
                $query->where('slug', $slug);
            });
        });

        $query->when($validated['deanery'] ?? null, function (Builder $query, string $slug) {
            $query->whereHas('deanery', function (Builder $query) use ($slug) {
                $query->where('slug', $slug);
            });
        });

        $query->when($validated['sanctity'] ?? null, function (Builder $query, string $slug) {
            $query->whereHas('sanctities', function (Builder $query) use ($slug) {
                $query->where('slug', $slug);
            });
        });

        $sort = $validated['sort'] ?? 'none';

        if ($sort === 'popular') {
            $query
                ->orderByDesc('popularity_count')
                ->orderByDesc('published_reviews_count')
                ->orderBy('name');
        } elseif ($sort === 'reviews') {
            $query
                ->whereHas('reviews', fn (Builder $query) => $query->where('status', 'published'))
                ->orderByDesc('published_reviews_count')
                ->orderByDesc('published_rating')
                ->orderBy('name');
        } elseif ($sort === 'newest') {
            $query->orderByDesc('published_at')->orderByDesc('id');
        } else {
            $query->orderBy('name');
        }

        return PilgrimageObjectResource::collection(
            $query->paginate($validated['per_page'] ?? 15)->withQueryString()
        );
    }

    public function show(
        Request $request,
        PilgrimageObject $pilgrimageObject,
        AnalyticsService $analytics
    ): PilgrimageObjectResource {
        $pilgrimageObject->loadMissing('objectType');
        $isScheduledForFuture = $pilgrimageObject->published_at
            && $pilgrimageObject->published_at->isFuture();
        $typeIsVisible = $pilgrimageObject->objectType
            && $pilgrimageObject->objectType->is_active
            && $pilgrimageObject->objectType->is_public;

        abort_if(! $pilgrimageObject->is_published || $isScheduledForFuture || ! $typeIsVisible, 404);

        $pilgrimageObject->load([
            'objectType',
            'parentObject.objectType',
            'publishedChildObjects.objectType',
            'vicariate',
            'deanery',
            'coverMedia',
            'sanctities',
            'media',
            'publishedPointsOfInterest.pilgrimageObject',
        ]);

        $pilgrimageObject->setRelation(
            'nearbyObjects',
            $this->nearbyObjects($pilgrimageObject)
        );

        $analytics->track($request, 'object_view', $pilgrimageObject, [
            'source' => 'mobile_api',
            'type' => $pilgrimageObject->objectType?->slug,
            'vicariate_id' => $pilgrimageObject->vicariate_id,
            'deanery_id' => $pilgrimageObject->deanery_id,
        ]);

        return new PilgrimageObjectResource($pilgrimageObject);
    }

    private function nearbyObjects(PilgrimageObject $object, float $radiusKm = 25, int $limit = 6): Collection
    {
        return PilgrimageObject::query()
            ->published()
            ->where('id', '<>', $object->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['objectType', 'coverMedia', 'parentObject.objectType'])
            ->get()
            ->map(function (PilgrimageObject $candidate) use ($object) {
                $candidate->setAttribute('distance_km', $this->distanceKm(
                    (float) $object->latitude,
                    (float) $object->longitude,
                    (float) $candidate->latitude,
                    (float) $candidate->longitude,
                ));

                return $candidate;
            })
            ->filter(fn (PilgrimageObject $candidate) => (float) $candidate->distance_km <= $radiusKm)
            ->sortBy('distance_km')
            ->take($limit)
            ->values();
    }

    private function distanceKm(float $latitude1, float $longitude1, float $latitude2, float $longitude2): float
    {
        $latitudeDelta = deg2rad($latitude2 - $latitude1);
        $longitudeDelta = deg2rad($longitude2 - $longitude1);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude1))
            * cos(deg2rad($latitude2))
            * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
