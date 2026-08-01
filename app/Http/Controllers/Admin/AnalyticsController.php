<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $from = Carbon::parse($filters['date_from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($filters['date_to'] ?? now()->toDateString())->endOfDay();

        if ($from->gt($to)) {
            throw ValidationException::withMessages(['date_from' => 'Начальная дата должна быть раньше конечной.']);
        }

        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['date_from' => 'За один раз можно анализировать период не более 366 дней.']);
        }

        $counts = $this->baseQuery($from, $to)
            ->selectRaw('event, COUNT(*) as aggregate')
            ->groupBy('event')
            ->pluck('aggregate', 'event');

        $summary = [
            'events' => (int) $counts->sum(),
            'sessions' => $this->baseQuery($from, $to)->whereNotNull('session_id')->distinct('session_id')->count('session_id'),
            'searches' => (int) ($counts['catalog_search'] ?? 0),
            'no_results' => (int) ($counts['search_no_results'] ?? 0),
            'object_views' => (int) ($counts['object_view'] ?? 0),
            'route_views' => (int) ($counts['route_view'] ?? 0),
            'day_routes' => (int) ($counts['day_route_generated'] ?? 0),
            'map_routes' => (int) ($counts['map_route_built'] ?? 0),
            'favorites' => (int) ($counts['favorite_added'] ?? 0),
            'booking_started' => (int) ($counts['booking_form_started'] ?? 0),
            'booking_submitted' => (int) ($counts['booking_submit_attempt'] ?? 0),
            'booking_created' => (int) ($counts['booking_created'] ?? 0),
            'booking_cancelled' => (int) ($counts['booking_cancelled'] ?? 0),
        ];

        $topSearches = $this->groupedQueries($from, $to, 'catalog_search');
        $noResultSearches = $this->groupedQueries($from, $to, 'search_no_results');
        $usedFilters = $this->groupedQueries($from, $to, 'catalog_filter');
        $topObjects = $this->topEntities($from, $to, 'object_view', PilgrimageObject::class);
        $topRoutes = $this->topEntities($from, $to, 'route_view', PilgrimageRoute::class);

        $daily = $this->baseQuery($from, $to)
            ->selectRaw('DATE(created_at) as event_date, COUNT(*) as aggregate')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('event_date')
            ->get()
            ->map(fn ($row): array => [
                'date' => Carbon::parse($row->event_date)->format('d.m'),
                'count' => (int) $row->aggregate,
            ]);

        $recent = $this->baseQuery($from, $to)
            ->with('user:id,name')
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.analytics.index', [
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'summary' => $summary,
            'topSearches' => $topSearches,
            'noResultSearches' => $noResultSearches,
            'usedFilters' => $usedFilters,
            'topObjects' => $topObjects,
            'topRoutes' => $topRoutes,
            'daily' => $daily,
            'recent' => $recent,
            'eventLabels' => $this->eventLabels(),
        ]);
    }

    private function baseQuery(Carbon $from, Carbon $to): Builder
    {
        return AnalyticsEvent::query()->whereBetween('created_at', [$from, $to]);
    }

    private function groupedQueries(Carbon $from, Carbon $to, string $event)
    {
        return $this->baseQuery($from, $to)
            ->where('event', $event)
            ->whereNotNull('search_query')
            ->where('search_query', '<>', '')
            ->selectRaw('search_query, COUNT(*) as aggregate')
            ->groupBy('search_query')
            ->orderByDesc('aggregate')
            ->limit(20)
            ->get();
    }

    private function topEntities(Carbon $from, Carbon $to, string $event, string $modelClass)
    {
        $rows = $this->baseQuery($from, $to)
            ->where('event', $event)
            ->whereNotNull('entity_id')
            ->selectRaw('entity_id, COUNT(*) as aggregate')
            ->groupBy('entity_id')
            ->orderByDesc('aggregate')
            ->limit(15)
            ->get();

        $models = $modelClass::withTrashed()
            ->whereKey($rows->pluck('entity_id')->all())
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($models): array {
            $model = $models->get((int) $row->entity_id);

            return [
                'id' => (int) $row->entity_id,
                'name' => $model?->name ?? $model?->title ?? 'Удалённая запись #'.$row->entity_id,
                'count' => (int) $row->aggregate,
                'model' => $model,
            ];
        });
    }

    private function eventLabels(): array
    {
        return [
            'catalog_search' => 'Поиск по каталогу',
            'search_no_results' => 'Поиск без результатов',
            'catalog_filter' => 'Использован фильтр каталога',
            'route_search' => 'Поиск маршрута',
            'object_view' => 'Просмотр объекта',
            'route_view' => 'Просмотр маршрута',
            'day_route_generated' => 'Сформирован маршрут дня',
            'map_route_built' => 'Построен путь на карте',
            'favorite_added' => 'Добавлено в избранное',
            'favorite_removed' => 'Удалено из избранного',
            'booking_form_started' => 'Открыта форма бронирования',
            'booking_submit_attempt' => 'Отправлена форма бронирования',
            'booking_created' => 'Создано бронирование',
            'booking_cancelled' => 'Бронирование отменено',
        ];
    }
}
