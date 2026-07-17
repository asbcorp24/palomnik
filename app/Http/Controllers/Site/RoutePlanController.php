<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageObject;
use App\Models\UserRoutePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoutePlanController extends Controller
{
    public function index(Request $request): View
    {
        $plans = $request->user()->routePlans()
            ->withCount('objects')
            ->latest()
            ->paginate(12);

        return view('site.route-plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('site.route-plans.form', [
            'plan' => new UserRoutePlan(),
            'objects' => $this->objects(),
            'transportModes' => $this->transportModes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $plan = $request->user()->routePlans()->create([
            'name' => $data['name'],
            'transport_mode' => $data['transport_mode'],
            'estimated_minutes' => $this->estimateMinutes($data['object_ids'], $data['transport_mode']),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncObjects($plan, $data['object_ids']);

        return redirect()->route('route-plans.show', $plan)
            ->with('success', 'Персональный маршрут сохранён.');
    }

    public function show(Request $request, UserRoutePlan $plan): View
    {
        $this->authorizeOwner($request, $plan);
        $plan->load(['objects.objectType', 'objects.coverMedia']);

        return view('site.route-plans.show', [
            'plan' => $plan,
            'transportModes' => $this->transportModes(),
            'yandexRouteUrl' => $this->yandexRouteUrl($plan),
        ]);
    }

    public function edit(Request $request, UserRoutePlan $plan): View
    {
        $this->authorizeOwner($request, $plan);
        $plan->load('objects');

        return view('site.route-plans.form', [
            'plan' => $plan,
            'objects' => $this->objects(),
            'transportModes' => $this->transportModes(),
        ]);
    }

    public function update(Request $request, UserRoutePlan $plan): RedirectResponse
    {
        $this->authorizeOwner($request, $plan);
        $data = $this->validated($request);

        $plan->update([
            'name' => $data['name'],
            'transport_mode' => $data['transport_mode'],
            'estimated_minutes' => $this->estimateMinutes($data['object_ids'], $data['transport_mode']),
            'notes' => $data['notes'] ?? null,
        ]);
        $this->syncObjects($plan, $data['object_ids']);

        return redirect()->route('route-plans.show', $plan)
            ->with('success', 'Маршрут обновлён.');
    }

    public function destroy(Request $request, UserRoutePlan $plan): RedirectResponse
    {
        $this->authorizeOwner($request, $plan);
        $plan->delete();

        return redirect()->route('route-plans.index')->with('success', 'Маршрут удалён.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'transport_mode' => ['required', Rule::in(array_keys($this->transportModes()))],
            'object_ids' => ['required', 'array', 'min:2', 'max:20'],
            'object_ids.*' => ['required', 'integer', 'distinct', 'exists:pilgrimage_objects,id'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function syncObjects(UserRoutePlan $plan, array $objectIds): void
    {
        $sync = [];
        foreach (array_values($objectIds) as $index => $objectId) {
            $sync[$objectId] = [
                'sort_order' => $index + 1,
                'stay_minutes' => 30,
            ];
        }
        $plan->objects()->sync($sync);
    }

    private function estimateMinutes(array $objectIds, string $transportMode): int
    {
        $objects = PilgrimageObject::query()
            ->whereIn('id', $objectIds)
            ->get(['id', 'latitude', 'longitude'])
            ->keyBy('id');

        $ordered = collect($objectIds)
            ->map(fn ($id) => $objects->get((int) $id))
            ->filter()
            ->values();

        $distanceKm = $this->routeDistanceKm($ordered);
        $speedKmH = match ($transportMode) {
            'walk' => 4.5,
            'public' => 18.0,
            'car' => 30.0,
            default => 20.0,
        };

        $travelMinutes = (int) ceil(($distanceKm / $speedKmH) * 60);
        $stopsMinutes = $ordered->count() * 30;
        $transferMinutes = $transportMode === 'public'
            ? max(0, $ordered->count() - 1) * 8
            : 0;

        return max(1, $travelMinutes + $stopsMinutes + $transferMinutes);
    }

    private function routeDistanceKm(Collection $objects): float
    {
        $distance = 0.0;

        for ($index = 1; $index < $objects->count(); $index++) {
            $from = $objects[$index - 1];
            $to = $objects[$index];
            $distance += $this->distanceKm(
                (float) $from->latitude,
                (float) $from->longitude,
                (float) $to->latitude,
                (float) $to->longitude
            );
        }

        return $distance;
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function authorizeOwner(Request $request, UserRoutePlan $plan): void
    {
        abort_unless($plan->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
    }

    private function objects()
    {
        return PilgrimageObject::query()
            ->published()
            ->with(['objectType', 'vicariate'])
            ->orderBy('name')
            ->get();
    }

    private function transportModes(): array
    {
        return [
            'walk' => 'Пешком',
            'public' => 'Общественный транспорт',
            'car' => 'Автомобиль',
        ];
    }

    private function yandexRouteUrl(UserRoutePlan $plan): string
    {
        $points = $plan->objects
            ->map(fn (PilgrimageObject $object) => $object->latitude.','.$object->longitude)
            ->implode('~');

        $routeType = [
            'walk' => 'pd',
            'public' => 'mt',
            'car' => 'auto',
        ][$plan->transport_mode] ?? 'auto';

        return 'https://yandex.ru/maps/?mode=routes&rtext='.rawurlencode($points).'&rtt='.$routeType;
    }
}
