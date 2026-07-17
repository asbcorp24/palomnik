<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformModuleController extends Controller
{
    public function index(Request $request, string $resource): View
    {
        $config = $this->config($resource);
        $search = trim((string) $request->query('q'));
        $status = trim((string) $request->query('status'));
        $query = $config['model']::query();

        if (! empty($config['with'])) {
            $query->with($config['with']);
        }

        if (! empty($config['with_count'])) {
            $query->withCount($config['with_count']);
        }

        if ($search !== '') {
            $query->where(function (Builder $query) use ($resource, $search) {
                if ($resource === 'trips') {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('meeting_point', 'like', "%{$search}%")
                        ->orWhereHas('pilgrimageRoute', function (Builder $query) use ($search) {
                            $query->where('title', 'like', "%{$search}%");
                        });
                } else {
                    $query->where($resource === 'routes' ? 'title' : 'title', 'like', "%{$search}%");
                }
            });
        }

        if ($status !== '' && isset($config['status_field'])) {
            $query->where($config['status_field'], $status);
        }

        $items = $query
            ->orderBy($config['order_by'], $config['order_direction'])
            ->paginate(20)
            ->withQueryString();

        return view('admin.modules.index', compact('resource', 'config', 'items', 'search', 'status'));
    }

    public function create(string $resource): View
    {
        $config = $this->config($resource);
        $item = new $config['model'];

        return view('admin.modules.form', [
            'resource' => $resource,
            'config' => $config,
            'item' => $item,
            'options' => $this->options($resource),
            'selectedObjectIds' => [],
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $config = $this->config($resource);
        $data = $this->validated($request, $resource);
        $objectIds = $data['object_ids'] ?? [];
        unset($data['object_ids']);
        $data = $this->transform($request, $resource, $data);

        $item = $config['model']::query()->create($data);

        if ($resource === 'routes') {
            $this->syncRouteObjects($item, $objectIds);
        }

        return redirect()
            ->route('admin.modules.edit', [$resource, $item->getKey()])
            ->with('success', $config['single'].' создан.');
    }

    public function edit(string $resource, int $id): View
    {
        $config = $this->config($resource);
        $item = $config['model']::query()->findOrFail($id);

        if ($resource === 'routes') {
            $item->load('objects');
        }

        return view('admin.modules.form', [
            'resource' => $resource,
            'config' => $config,
            'item' => $item,
            'options' => $this->options($resource),
            'selectedObjectIds' => $resource === 'routes' ? $item->objects->pluck('id')->all() : [],
        ]);
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $config = $this->config($resource);
        $item = $config['model']::query()->findOrFail($id);
        $data = $this->validated($request, $resource, $item);
        $objectIds = $data['object_ids'] ?? [];
        unset($data['object_ids']);
        $data = $this->transform($request, $resource, $data, $item);
        $item->update($data);

        if ($resource === 'routes') {
            $this->syncRouteObjects($item, $objectIds);
        }

        return redirect()
            ->route('admin.modules.edit', [$resource, $item->getKey()])
            ->with('success', $config['single'].' обновлён.');
    }

    public function destroy(string $resource, int $id): RedirectResponse
    {
        $config = $this->config($resource);
        $item = $config['model']::query()->findOrFail($id);
        $item->delete();

        return redirect()
            ->route('admin.modules.index', $resource)
            ->with('success', $config['single'].' удалён.');
    }

    private function validated(Request $request, string $resource, ?Model $item = null): array
    {
        if ($resource === 'routes') {
            $slugRule = Rule::unique('pilgrimage_routes', 'slug');
            if ($item) {
                $slugRule->ignore($item->getKey());
            }

            return $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'slug' => ['nullable', 'string', 'max:255', $slugRule],
                'category' => ['required', Rule::in(array_keys($this->routeCategories()))],
                'difficulty' => ['required', Rule::in(array_keys($this->difficulties()))],
                'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
                'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
                'base_price' => ['nullable', 'numeric', 'min:0'],
                'short_description' => ['nullable', 'string', 'max:2000'],
                'description' => ['nullable', 'string'],
                'program' => ['nullable', 'string'],
                'is_group' => ['nullable', 'boolean'],
                'is_published' => ['nullable', 'boolean'],
                'published_at' => ['nullable', 'date'],
                'object_ids' => ['nullable', 'array'],
                'object_ids.*' => ['integer', 'exists:pilgrimage_objects,id'],
            ]);
        }

        if ($resource === 'trips') {
            return $request->validate([
                'pilgrimage_route_id' => ['required', 'integer', 'exists:pilgrimage_routes,id'],
                'title' => ['nullable', 'string', 'max:255'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
                'meeting_point' => ['nullable', 'string', 'max:255'],
                'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
                'price' => ['nullable', 'numeric', 'min:0'],
                'status' => ['required', Rule::in(array_keys($this->tripStatuses()))],
                'notes' => ['nullable', 'string'],
            ]);
        }

        $slugRule = Rule::unique('achievements', 'slug');
        if ($item) {
            $slugRule->ignore($item->getKey());
        }

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $slugRule],
            'category' => ['required', Rule::in(array_keys($this->achievementCategories()))],
            'badge_level' => ['required', Rule::in(array_keys($this->badgeLevels()))],
            'points' => ['required', 'integer', 'min:0', 'max:1000000'],
            'condition_type' => ['required', 'string', 'max:64'],
            'condition_value' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function transform(Request $request, string $resource, array $data, ?Model $item = null): array
    {
        if (in_array($resource, ['routes', 'achievements'], true)) {
            $base = Str::slug($data['slug'] ?: $data['title']);
            $base = $base !== '' ? $base : Str::random(12);
            $data['slug'] = $this->uniqueSlug($resource, $base, $item?->getKey());
        }

        if ($resource === 'routes') {
            $data['is_group'] = $request->boolean('is_group');
            $data['is_published'] = $request->boolean('is_published');
            $data['published_at'] = $data['is_published']
                ? ($data['published_at'] ?? $item?->published_at ?? now())
                : null;
        }

        if ($resource === 'achievements') {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }

    private function syncRouteObjects(PilgrimageRoute $route, array $objectIds): void
    {
        $sync = [];
        foreach (array_values(array_unique($objectIds)) as $index => $objectId) {
            $sync[$objectId] = ['sort_order' => $index + 1];
        }
        $route->objects()->sync($sync);
    }

    private function uniqueSlug(string $resource, string $base, ?int $ignoreId = null): string
    {
        $model = $resource === 'routes' ? PilgrimageRoute::class : Achievement::class;
        $candidate = $base;
        $counter = 2;

        while ($model::query()
            ->where('slug', $candidate)
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '<>', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$counter++;
        }

        return $candidate;
    }

    private function options(string $resource): array
    {
        return [
            'route_categories' => $this->routeCategories(),
            'difficulties' => $this->difficulties(),
            'trip_statuses' => $this->tripStatuses(),
            'achievement_categories' => $this->achievementCategories(),
            'badge_levels' => $this->badgeLevels(),
            'routes' => $resource === 'trips'
                ? PilgrimageRoute::query()->orderBy('title')->get(['id', 'title'])
                : collect(),
            'objects' => $resource === 'routes'
                ? PilgrimageObject::query()->orderBy('name')->get(['id', 'name', 'address'])
                : collect(),
        ];
    }

    private function routeCategories(): array
    {
        return [
            'one_day' => 'Однодневный',
            'multi_day' => 'Многодневный',
            'thematic' => 'Тематический',
            'family' => 'Семейный',
            'youth' => 'Молодёжный',
            'individual' => 'Индивидуальный',
        ];
    }

    private function difficulties(): array
    {
        return [
            'easy' => 'Лёгкий',
            'medium' => 'Средний',
            'hard' => 'Сложный',
        ];
    }

    private function tripStatuses(): array
    {
        return [
            'planned' => 'Запланирована',
            'open' => 'Открыта запись',
            'closed' => 'Запись закрыта',
            'cancelled' => 'Отменена',
            'completed' => 'Завершена',
        ];
    }

    private function achievementCategories(): array
    {
        return [
            'visits' => 'Посещения',
            'thematic_route' => 'Тематический маршрут',
            'family_trips' => 'Семейные поездки',
            'activity' => 'Активность',
            'special' => 'Специальное',
        ];
    }

    private function badgeLevels(): array
    {
        return [
            'bronze' => 'Бронза',
            'silver' => 'Серебро',
            'gold' => 'Золото',
            'special' => 'Уникальный',
        ];
    }

    private function config(string $resource): array
    {
        $resources = [
            'routes' => [
                'model' => PilgrimageRoute::class,
                'title' => 'Паломнические маршруты',
                'single' => 'Маршрут',
                'icon' => 'bi-signpost-split',
                'with' => [],
                'with_count' => ['objects', 'trips'],
                'order_by' => 'updated_at',
                'order_direction' => 'desc',
                'status_field' => 'is_published',
                'statuses' => ['1' => 'Опубликованные', '0' => 'Черновики'],
                'columns' => [
                    ['key' => 'title', 'label' => 'Маршрут'],
                    ['key' => 'category', 'label' => 'Категория', 'map' => $this->routeCategories()],
                    ['key' => 'duration_days', 'label' => 'Дней'],
                    ['key' => 'objects_count', 'label' => 'Точек'],
                    ['key' => 'trips_count', 'label' => 'Поездок'],
                    ['key' => 'is_published', 'label' => 'Статус', 'type' => 'boolean'],
                ],
            ],
            'trips' => [
                'model' => Trip::class,
                'title' => 'Расписание поездок',
                'single' => 'Поездка',
                'icon' => 'bi-calendar3',
                'with' => ['pilgrimageRoute'],
                'with_count' => ['bookings'],
                'order_by' => 'starts_at',
                'order_direction' => 'desc',
                'status_field' => 'status',
                'statuses' => $this->tripStatuses(),
                'columns' => [
                    ['key' => 'pilgrimageRoute.title', 'label' => 'Маршрут'],
                    ['key' => 'starts_at', 'label' => 'Начало', 'type' => 'datetime'],
                    ['key' => 'capacity', 'label' => 'Мест'],
                    ['key' => 'bookings_count', 'label' => 'Бронирований'],
                    ['key' => 'price', 'label' => 'Цена', 'type' => 'money'],
                    ['key' => 'status', 'label' => 'Статус', 'map' => $this->tripStatuses(), 'type' => 'status'],
                ],
            ],
            'achievements' => [
                'model' => Achievement::class,
                'title' => 'Геймификация и достижения',
                'single' => 'Достижение',
                'icon' => 'bi-trophy',
                'with' => [],
                'with_count' => ['users'],
                'order_by' => 'points',
                'order_direction' => 'asc',
                'status_field' => 'is_active',
                'statuses' => ['1' => 'Активные', '0' => 'Отключённые'],
                'columns' => [
                    ['key' => 'title', 'label' => 'Достижение'],
                    ['key' => 'badge_level', 'label' => 'Значок', 'map' => $this->badgeLevels()],
                    ['key' => 'points', 'label' => 'Баллы'],
                    ['key' => 'condition_value', 'label' => 'Условие'],
                    ['key' => 'users_count', 'label' => 'Получили'],
                    ['key' => 'is_active', 'label' => 'Статус', 'type' => 'boolean'],
                ],
            ],
        ];

        abort_unless(isset($resources[$resource]), 404);

        return $resources[$resource];
    }
}
