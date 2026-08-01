<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Deanery;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\PointOfInterest;
use App\Models\Sanctity;
use App\Models\Vicariate;
use App\Services\AnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MapController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'vicariate' => ['nullable', 'string', 'max:255'],
            'deanery' => ['nullable', 'string', 'max:255'],
            'sanctity' => ['nullable', 'string', 'max:255'],
            'route' => ['nullable', 'string', 'max:255'],
            'focus_poi' => ['nullable', 'integer', 'exists:points_of_interest,id'],
        ]);

        $this->trackFilters($request, $analytics, $filters);

        $routes = PilgrimageRoute::query()
            ->published()
            ->withCount(['objects' => fn ($query) => $query->published()])
            ->orderBy('title')
            ->get();

        $selectedRoute = null;
        if (! empty($filters['route'])) {
            $route = PilgrimageRoute::query()
                ->published()
                ->where('slug', $filters['route'])
                ->with(['objects' => function ($query) {
                    $query->published()
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude');
                }])
                ->first();

            if ($route) {
                $points = $this->routePoints($route->objects);

                if ($points->count() >= 2) {
                    $selectedRoute = [
                        'id' => $route->id,
                        'slug' => $route->slug,
                        'title' => $route->title,
                        'category' => $route->category,
                        'difficulty' => $route->difficulty,
                        'duration_minutes' => $route->duration_minutes,
                        'url' => route('routes.show', $route),
                        'points' => $points,
                    ];
                }
            }
        }

        return view('site.map', [
            'filters' => $filters,
            'types' => ObjectType::query()->visible()->orderBy('sort_order')->orderBy('name')->get(),
            'vicariates' => Vicariate::query()->orderBy('name')->get(),
            'deaneries' => Deanery::query()->with('vicariate')->orderBy('name')->get(),
            'sanctities' => Sanctity::query()->where('slug', '<>', 'holy-spring')->orderBy('name')->limit(300)->get(),
            'routes' => $routes,
            'selectedRoute' => $selectedRoute,
            'focusedPointOfInterestId' => isset($filters['focus_poi']) ? (int) $filters['focus_poi'] : null,
            'poiCategories' => PointOfInterest::CATEGORIES,
        ]);
    }

    private function trackFilters(Request $request, AnalyticsService $analytics, array $filters): void
    {
        $searchTerm = trim((string) ($filters['q'] ?? ''));
        $activeFilters = collect($filters)
            ->only(['type', 'vicariate', 'deanery', 'sanctity'])
            ->filter(fn ($value): bool => filled($value));

        if ($searchTerm === '' && $activeFilters->isEmpty()) {
            return;
        }

        $resultCount = $this->filteredObjectQuery($filters)->count();

        if ($searchTerm !== '') {
            $analytics->track($request, 'catalog_search', null, [
                'source' => 'map',
                'results_count' => $resultCount,
                'type' => $filters['type'] ?? null,
                'vicariate' => $filters['vicariate'] ?? null,
                'deanery' => $filters['deanery'] ?? null,
                'sanctity' => $filters['sanctity'] ?? null,
            ], $searchTerm);

            if ($resultCount === 0) {
                $analytics->track($request, 'search_no_results', null, [
                    'source' => 'map',
                    'type' => $filters['type'] ?? null,
                    'vicariate' => $filters['vicariate'] ?? null,
                    'deanery' => $filters['deanery'] ?? null,
                    'sanctity' => $filters['sanctity'] ?? null,
                ], $searchTerm);
            }
        }

        if ($activeFilters->isNotEmpty()) {
            $analytics->track(
                $request,
                'catalog_filter',
                null,
                ['source' => 'map', 'results_count' => $resultCount] + $activeFilters->all(),
                'map: '.$activeFilters->map(fn ($value, $key): string => $key.'='.$value)->implode('; ')
            );
        }
    }

    private function filteredObjectQuery(array $filters): Builder
    {
        return PilgrimageObject::query()
            ->published()
            ->search($filters['q'] ?? null)
            ->when($filters['type'] ?? null, fn (Builder $query, string $slug) => $query->whereHas(
                'objectType',
                fn (Builder $query) => $query->visible()->where('slug', $slug)
            ))
            ->when($filters['vicariate'] ?? null, fn (Builder $query, string $slug) => $query->whereHas(
                'vicariate',
                fn (Builder $query) => $query->where('slug', $slug)
            ))
            ->when($filters['deanery'] ?? null, fn (Builder $query, string $slug) => $query->whereHas(
                'deanery',
                fn (Builder $query) => $query->where('slug', $slug)
            ))
            ->when($filters['sanctity'] ?? null, fn (Builder $query, string $slug) => $query->whereHas(
                'sanctities',
                fn (Builder $query) => $query->where('slug', $slug)
            ));
    }

    private function routePoints(Collection $objects): Collection
    {
        return $objects
            ->filter(fn (PilgrimageObject $object) => is_numeric($object->latitude) && is_numeric($object->longitude))
            ->values()
            ->map(fn (PilgrimageObject $object, int $index) => [
                'number' => $index + 1,
                'id' => $object->id,
                'name' => $object->name,
                'address' => $object->address,
                'latitude' => (float) $object->latitude,
                'longitude' => (float) $object->longitude,
                'stay_minutes' => $object->pivot->stay_minutes,
                'note' => $object->pivot->note,
                'url' => route('objects.show', $object),
            ]);
    }
}
