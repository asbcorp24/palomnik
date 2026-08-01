<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Deanery;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\Vicariate;
use App\Services\AnalyticsService;
use App\Services\PilgrimageObjectFuzzySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ObjectController extends Controller
{
    public function index(
        Request $request,
        PilgrimageObjectFuzzySearch $fuzzySearch,
        AnalyticsService $analytics
    ): View {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'vicariate' => ['nullable', 'string', 'max:255'],
            'deanery' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:name,newest'],
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
                'publishedChildObjects.sanctities',
            ])
            ->withCount('publishedChildObjects')
            ->withAvg(['reviews as published_rating' => fn ($query) => $query->where('status', 'published')], 'rating')
            ->typeOrParentOfType($filters['type'] ?? null)
            ->when($filters['vicariate'] ?? null, function (Builder $query, string $slug) {
                $query->whereHas('vicariate', fn (Builder $query) => $query->where('slug', $slug));
            })
            ->when($filters['deanery'] ?? null, function (Builder $query, string $slug) {
                $query->whereHas('deanery', fn (Builder $query) => $query->where('slug', $slug));
            });

        $searchTerm = trim((string) ($filters['q'] ?? ''));

        if ($searchTerm !== '') {
            $rankedObjects = $fuzzySearch->rank($query->get(), $searchTerm);

            if ($request->filled('sort')) {
                $rankedObjects = ($filters['sort'] ?? 'name') === 'newest'
                    ? $rankedObjects->sortByDesc(fn (PilgrimageObject $object) => $object->published_at?->getTimestamp() ?? 0)->values()
                    : $rankedObjects->sortBy(fn (PilgrimageObject $object) => mb_strtolower($object->name, 'UTF-8'))->values();
            }

            $objects = $this->paginateCollection($rankedObjects, $request, 12);
        } else {
            if (($filters['sort'] ?? 'name') === 'newest') {
                $query->orderByDesc('published_at')->orderByDesc('id');
            } else {
                $query->orderBy('name');
            }

            $objects = $query->paginate(12)->withQueryString();
        }

        if ($searchTerm !== '') {
            $analytics->track($request, 'catalog_search', null, [
                'results_count' => $objects->total(),
                'type' => $filters['type'] ?? null,
                'vicariate' => $filters['vicariate'] ?? null,
                'deanery' => $filters['deanery'] ?? null,
            ], $searchTerm);

            if ($objects->total() === 0) {
                $analytics->track($request, 'search_no_results', null, [
                    'type' => $filters['type'] ?? null,
                    'vicariate' => $filters['vicariate'] ?? null,
                    'deanery' => $filters['deanery'] ?? null,
                ], $searchTerm);
            }
        }

        $activeFilters = collect($filters)
            ->except(['q'])
            ->filter(fn ($value): bool => filled($value));
        if ($activeFilters->isNotEmpty()) {
            $analytics->track(
                $request,
                'catalog_filter',
                null,
                $activeFilters->all(),
                $activeFilters->map(fn ($value, $key): string => $key.'='.$value)->implode('; ')
            );
        }

        return view('site.objects.index', [
            'objects' => $objects,
            'types' => ObjectType::query()->visible()->orderBy('sort_order')->orderBy('name')->get(),
            'vicariates' => Vicariate::query()->orderBy('name')->get(),
            'deaneries' => Deanery::query()->with('vicariate')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(
        Request $request,
        PilgrimageObject $object,
        AnalyticsService $analytics
    ): View {
        $object->loadMissing('objectType');
        $isScheduledForFuture = $object->published_at && $object->published_at->isFuture();
        $typeIsVisible = $object->objectType
            && $object->objectType->is_active
            && $object->objectType->is_public;

        abort_if(! $object->is_published || $isScheduledForFuture || ! $typeIsVisible, 404);

        $object->load([
            'objectType',
            'parentObject.objectType',
            'publishedChildObjects.objectType',
            'vicariate',
            'deanery',
            'coverMedia',
            'sanctities',
            'media',
            'reviews' => fn ($query) => $query->where('status', 'published')->with('user')->latest(),
            'userMedia' => fn ($query) => $query->where('status', 'published')->with('user')->latest(),
            'pointsOfInterest' => fn ($query) => $query->published()->ordered(),
        ]);

        $analytics->track($request, 'object_view', $object, [
            'type' => $object->objectType?->slug,
            'vicariate_id' => $object->vicariate_id,
            'deanery_id' => $object->deanery_id,
        ]);

        $nearbyObjects = $this->nearbyObjects($object);

        $userReview = auth()->check()
            ? auth()->user()->reviews()->where('pilgrimage_object_id', $object->id)->first()
            : null;

        $favoriteLists = auth()->check()
            ? auth()->user()->favoriteLists()->orderByDesc('is_default')->orderBy('name')->get()
            : collect();

        $isFavorite = auth()->check()
            && auth()->user()->favoriteLists()
                ->whereHas('objects', fn ($query) => $query->whereKey($object->id))
                ->exists();

        return view('site.objects.show', [
            'object' => $object,
            'nearbyObjects' => $nearbyObjects,
            'userReview' => $userReview,
            'favoriteLists' => $favoriteLists,
            'isFavorite' => $isFavorite,
            'rating' => $object->reviews->avg('rating'),
        ]);
    }

    private function nearbyObjects(PilgrimageObject $object, float $radiusKm = 25, int $limit = 6): Collection
    {
        $latitude = (float) $object->latitude;
        $longitude = (float) $object->longitude;
        $latitudeDelta = $radiusKm / 111.32;
        $longitudeScale = max(0.1, cos(deg2rad($latitude)));
        $longitudeDelta = $radiusKm / (111.32 * $longitudeScale);

        return PilgrimageObject::query()
            ->published()
            ->where('id', '<>', $object->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
            ->with(['objectType', 'vicariate', 'coverMedia', 'parentObject.objectType'])
            ->withCount('publishedChildObjects')
            ->get()
            ->map(function (PilgrimageObject $candidate) use ($latitude, $longitude) {
                $candidate->setAttribute('distance_km', $this->distanceKm(
                    $latitude,
                    $longitude,
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

    private function paginateCollection(Collection $items, Request $request, int $perPage): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}
