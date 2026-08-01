<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageObject;
use App\Models\UserRoutePlan;
use App\Services\DayRoutePlannerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DayRouteController extends Controller
{
    private DayRoutePlannerService $planner;

    public function __construct(DayRoutePlannerService $planner)
    {
        $this->planner = $planner;
    }

    public function index(): View
    {
        return view('site.day-route.index', [
            'result' => null,
            'form' => $this->defaults(),
            'transportModes' => $this->transportModes(),
            'themes' => $this->themes(),
        ]);
    }

    public function generate(Request $request): View|RedirectResponse
    {
        $data = $this->validated($request);
        $data['allow_unknown_schedule'] = $request->boolean('allow_unknown_schedule');

        $result = $this->planner->plan($data);

        if (($result['summary']['objects_count'] ?? 0) < 2) {
            return back()
                ->withInput()
                ->with('error', 'Не удалось составить маршрут минимум из двух объектов. Увеличьте время или расстояние, разрешите неопределённое расписание либо выберите другую тему.');
        }

        $request->session()->put('day_route_save_payload', [
            'title' => $result['title'],
            'transport_mode' => $data['transport_mode'],
            'estimated_minutes' => (int) $result['summary']['total_minutes'],
            'distance_km' => (float) $result['summary']['distance_km'],
            'generated_at' => $result['generated_at'],
            'criteria' => $result['criteria'],
            'warnings' => $result['warnings'],
            'stops' => collect($result['stops'])->map(fn (array $stop): array => [
                'id' => (int) $stop['id'],
                'stay_minutes' => (int) $stop['stay_minutes'],
                'arrival_at' => $stop['arrival_at'],
                'departure_at' => $stop['departure_at'],
                'schedule_status' => $stop['schedule_status'],
            ])->values()->all(),
        ]);

        return view('site.day-route.index', [
            'result' => $result,
            'form' => $data,
            'transportModes' => $this->transportModes(),
            'themes' => $this->themes(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $payload = $request->session()->get('day_route_save_payload');
        abort_unless(is_array($payload) && count($payload['stops'] ?? []) >= 2, 422, 'Сначала сформируйте маршрут дня.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $objectIds = collect($payload['stops'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $existingObjectIds = PilgrimageObject::query()
            ->published()
            ->whereKey($objectIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        abort_unless($existingObjectIds->count() === $objectIds->count(), 422, 'Некоторые объекты маршрута больше недоступны. Сформируйте маршрут заново.');

        $plan = DB::transaction(function () use ($request, $payload, $validated): UserRoutePlan {
            $plan = $request->user()->routePlans()->create([
                'name' => $validated['name'],
                'transport_mode' => $payload['transport_mode'],
                'estimated_minutes' => (int) $payload['estimated_minutes'],
                'notes' => $this->notes($payload),
            ]);

            $sync = [];
            foreach (array_values($payload['stops']) as $index => $stop) {
                $sync[(int) $stop['id']] = [
                    'sort_order' => $index + 1,
                    'stay_minutes' => (int) ($stop['stay_minutes'] ?? 30),
                ];
            }
            $plan->objects()->sync($sync);

            return $plan;
        });

        $request->session()->forget('day_route_save_payload');

        return redirect()
            ->route('route-plans.show', $plan)
            ->with('success', 'Маршрут дня сохранён в разделе «Мои маршруты».');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'location_label' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:54.5,57.5'],
            'longitude' => ['required', 'numeric', 'between:35,41.5'],
            'start_at' => ['required', 'date', 'after_or_equal:today'],
            'available_minutes' => ['required', 'integer', Rule::in([90, 120, 180, 240, 360, 480])],
            'transport_mode' => ['required', Rule::in(array_keys($this->transportModes()))],
            'object_count' => ['required', 'integer', 'min:2', 'max:8'],
            'max_distance_km' => ['required', 'numeric', 'min:1', 'max:120'],
            'theme' => ['required', Rule::in(array_keys($this->themes()))],
            'allow_unknown_schedule' => ['nullable', 'boolean'],
        ], [
            'latitude.required' => 'Укажите точку начала на карте или используйте текущее местоположение.',
            'longitude.required' => 'Укажите точку начала на карте или используйте текущее местоположение.',
            'start_at.after_or_equal' => 'Маршрут можно составить только на текущую или будущую дату.',
        ]);
    }

    private function defaults(): array
    {
        return [
            'location_label' => '',
            'latitude' => 55.751244,
            'longitude' => 37.618423,
            'start_at' => now()->addMinutes(30)->format('Y-m-d\TH:i'),
            'available_minutes' => 180,
            'transport_mode' => 'walk',
            'object_count' => 3,
            'max_distance_km' => 8,
            'theme' => 'any',
            'allow_unknown_schedule' => true,
        ];
    }

    private function transportModes(): array
    {
        return [
            'walk' => 'Пешком',
            'public' => 'Общественный транспорт',
            'car' => 'Автомобиль',
        ];
    }

    private function themes(): array
    {
        return [
            'any' => 'Без специальной темы',
            'monasteries' => 'Монастыри',
            'sanctities' => 'Храмы со святынями',
            'icons' => 'Почитаемые иконы',
            'relics' => 'Мощи святых',
            'history' => 'История и наследие',
            'accessible' => 'Доступная среда',
        ];
    }

    private function notes(array $payload): string
    {
        $criteria = $payload['criteria'] ?? [];
        $startsAt = isset($criteria['start_at'])
            ? Carbon::parse($criteria['start_at'])->format('d.m.Y H:i')
            : 'не указано';
        $transport = $this->transportModes()[$payload['transport_mode']] ?? $payload['transport_mode'];
        $theme = $this->themes()[$criteria['theme'] ?? 'any'] ?? ($criteria['theme'] ?? '');

        $lines = [
            'Сформировано функцией «Маршрут дня».',
            'Начало: '.($criteria['location_label'] ?: 'выбранная точка').' — '.$startsAt.'.',
            'Способ передвижения: '.$transport.'.',
            'Тема: '.$theme.'.',
            'Расстояние: '.number_format((float) ($payload['distance_km'] ?? 0), 1, ',', ' ').' км.',
            'Расчётная продолжительность: '.$this->formatDuration((int) $payload['estimated_minutes']).'.',
        ];

        foreach ($payload['warnings'] ?? [] as $warning) {
            $lines[] = 'Важно: '.$warning;
        }

        return implode("\n", $lines);
    }

    private function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return $rest.' мин.';
        }

        return $hours.' ч. '.($rest > 0 ? $rest.' мин.' : '');
    }
}
