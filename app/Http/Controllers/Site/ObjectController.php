<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Deanery;
use App\Models\ObjectMedia;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\Vicariate;
use App\Services\AnalyticsService;
use App\Services\PilgrimageObjectFuzzySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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
    ): View|JsonResponse {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'vicariate' => ['nullable', 'string', 'max:255'],
            'deanery' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:none,popular,reviews,name,newest'],
            'picker' => ['nullable', 'in:route'],
        ]);

        if (($filters['picker'] ?? null) === 'route') {
            return $this->routePickerResults($filters);
        }

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
            ->typeOrParentOfType($filters['type'] ?? null)
            ->when($filters['vicariate'] ?? null, function (Builder $query, string $slug) {
                $query->whereHas('vicariate', fn (Builder $query) => $query->where('slug', $slug));
            })
            ->when($filters['deanery'] ?? null, function (Builder $query, string $slug) {
                $query->whereHas('deanery', fn (Builder $query) => $query->where('slug', $slug));
            });

        $searchTerm = trim((string) ($filters['q'] ?? ''));
        $sort = $filters['sort'] ?? 'none';

        if ($searchTerm !== '') {
            $rankedObjects = $fuzzySearch->rank($query->get(), $searchTerm);

            if ($sort === 'popular') {
                $rankedObjects = $rankedObjects
                    ->sort(function (PilgrimageObject $first, PilgrimageObject $second): int {
                        $comparison = (int) $second->popularity_count <=> (int) $first->popularity_count;
                        if ($comparison !== 0) {
                            return $comparison;
                        }

                        $comparison = (int) $second->published_reviews_count <=> (int) $first->published_reviews_count;

                        return $comparison !== 0
                            ? $comparison
                            : strcasecmp($first->name, $second->name);
                    })
                    ->values();
            } elseif ($sort === 'reviews') {
                $rankedObjects = $rankedObjects
                    ->filter(fn (PilgrimageObject $object): bool => (int) $object->published_reviews_count > 0)
                    ->sort(function (PilgrimageObject $first, PilgrimageObject $second): int {
                        $comparison = (int) $second->published_reviews_count <=> (int) $first->published_reviews_count;
                        if ($comparison !== 0) {
                            return $comparison;
                        }

                        $comparison = (float) $second->published_rating <=> (float) $first->published_rating;

                        return $comparison !== 0
                            ? $comparison
                            : strcasecmp($first->name, $second->name);
                    })
                    ->values();
            } elseif ($sort === 'newest') {
                $rankedObjects = $rankedObjects
                    ->sortByDesc(fn (PilgrimageObject $object) => $object->published_at?->getTimestamp() ?? 0)
                    ->values();
            } elseif ($sort === 'name') {
                $rankedObjects = $rankedObjects
                    ->sortBy(fn (PilgrimageObject $object) => mb_strtolower($object->name, 'UTF-8'))
                    ->values();
            }

            $objects = $this->paginateCollection($rankedObjects, $request, 12);
        } else {
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

            $objects = $query->paginate(12)->withQueryString();
        }

        if ($searchTerm !== '') {
            $analytics->track($request, 'catalog_search', null, [
                'results_count' => $objects->total(),
                'type' => $filters['type'] ?? null,
                'vicariate' => $filters['vicariate'] ?? null,
                'deanery' => $filters['deanery'] ?? null,
                'sort' => $sort,
            ], $searchTerm);

            if ($objects->total() === 0) {
                $analytics->track($request, 'search_no_results', null, [
                    'type' => $filters['type'] ?? null,
                    'vicariate' => $filters['vicariate'] ?? null,
                    'deanery' => $filters['deanery'] ?? null,
                    'sort' => $sort,
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
            'audioGuide',
            'media',
            'reviews' => fn ($query) => $query->where('status', 'published')->with('user')->latest(),
            'userMedia' => fn ($query) => $query->where('status', 'published')->with('user')->latest(),
            'pointsOfInterest' => fn ($query) => $query->published()->ordered(),
        ]);

        if ($object->audioGuide?->url) {
            $mainAudioGuide = new ObjectMedia([
                'type' => 'audio',
                'path' => $object->audioGuide->path,
                'title' => $object->audioGuide->title ?: 'Аудиогид',
                'description' => $object->audioGuide->transcript,
                'sort_order' => -1,
                'is_cover' => false,
            ]);

            $object->setRelation('media', $object->media->prepend($mainAudioGuide));
        }

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

    private function routePickerResults(array $filters): JsonResponse
    {
        $searchTerm = trim((string) ($filters['q'] ?? ''));

        if (mb_strlen($searchTerm, 'UTF-8') < 2) {
            return response()->json(['data' => []]);
        }

        $type = in_array($filters['type'] ?? null, ['temple', 'monastery'], true)
            ? $filters['type']
            : null;

        $objects = PilgrimageObject::query()
            ->published()
            ->select(['id', 'name', 'address', 'object_type_id'])
            ->with('objectType:id,name,slug')
            ->whereHas('objectType', function (Builder $query) use ($type) {
                $query->visible()
                    ->whereIn('slug', ['temple', 'monastery'])
                    ->when($type, fn (Builder $query, string $slug) => $query->where('slug', $slug));
            })
            ->where(function (Builder $query) use ($searchTerm) {
                $query->where('name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('address', 'like', '%'.$searchTerm.'%');
            })
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$searchTerm.'%'])
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (PilgrimageObject $object): array => [
                'id' => (int) $object->id,
                'name' => $object->name,
                'address' => $object->address,
                'type' => $object->objectType?->name ?: 'Паломнический объект',
                'type_slug' => $object->objectType?->slug,
            ])
            ->values();

        return response()->json(['data' => $objects]);
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
