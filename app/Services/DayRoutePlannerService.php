<?php

namespace App\Services;

use App\Models\PilgrimageObject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DayRoutePlannerService
{
    private const STAY_MINUTES = 30;

    public function plan(array $criteria): array
    {
        $latitude = (float) $criteria['latitude'];
        $longitude = (float) $criteria['longitude'];
        $availableMinutes = (int) $criteria['available_minutes'];
        $requestedCount = (int) $criteria['object_count'];
        $maxDistanceKm = (float) $criteria['max_distance_km'];
        $transportMode = (string) $criteria['transport_mode'];
        $theme = (string) ($criteria['theme'] ?? 'any');
        $allowUnknownSchedule = (bool) ($criteria['allow_unknown_schedule'] ?? false);
        $startsAt = Carbon::parse($criteria['start_at']);

        $candidates = $this->candidateObjects(
            $latitude,
            $longitude,
            $maxDistanceKm,
            $theme
        );

        $selection = $this->selectStops(
            $candidates,
            $latitude,
            $longitude,
            $startsAt,
            $availableMinutes,
            $requestedCount,
            $maxDistanceKm,
            $transportMode,
            $allowUnknownSchedule
        );

        $selected = $selection['stops'];
        $routing = $this->routeMetrics(
            $latitude,
            $longitude,
            $selected,
            $transportMode
        );

        while (
            $selected->count() > 2
            && (
                $routing['distance_km'] > $maxDistanceKm
                || $routing['travel_minutes'] + ($selected->count() * self::STAY_MINUTES) > $availableMinutes
            )
        ) {
            $selected->pop();
            $routing = $this->routeMetrics(
                $latitude,
                $longitude,
                $selected,
                $transportMode
            );
        }

        $stops = $this->buildTimeline(
            $selected,
            $startsAt,
            $routing['leg_minutes'],
            $transportMode
        );

        $visitMinutes = $stops->count() * self::STAY_MINUTES;
        $totalMinutes = $routing['travel_minutes'] + $visitMinutes;
        $warnings = $selection['warnings'];

        if (! $routing['exact']) {
            $warnings[] = 'Точный расчёт дорожного пути временно недоступен. Использована оценка по расстоянию между координатами.';
        }

        if ($stops->count() < $requestedCount) {
            $warnings[] = 'В заданное время и расстояние удалось включить '.$stops->count().' из '.$requestedCount.' запрошенных объектов.';
        }

        if ($stops->contains(fn (array $stop): bool => $stop['schedule_status'] === 'unknown')) {
            $warnings[] = 'У части объектов расписание отсутствует или записано в свободной форме. Перед поездкой уточните часы посещения.';
        }

        $title = $this->routeTitle($stops, $criteria['location_label'] ?? null);
        $points = collect([[
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]])->concat($stops->map(fn (array $stop): array => [
            'latitude' => $stop['latitude'],
            'longitude' => $stop['longitude'],
        ]));

        return [
            'title' => $title,
            'generated_at' => now()->toIso8601String(),
            'criteria' => [
                'location_label' => trim((string) ($criteria['location_label'] ?? '')),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'start_at' => $startsAt->toIso8601String(),
                'available_minutes' => $availableMinutes,
                'transport_mode' => $transportMode,
                'object_count' => $requestedCount,
                'max_distance_km' => $maxDistanceKm,
                'theme' => $theme,
                'allow_unknown_schedule' => $allowUnknownSchedule,
            ],
            'summary' => [
                'distance_km' => round($routing['distance_km'], 1),
                'travel_minutes' => $routing['travel_minutes'],
                'visit_minutes' => $visitMinutes,
                'total_minutes' => $totalMinutes,
                'objects_count' => $stops->count(),
                'exact_routing' => $routing['exact'],
            ],
            'start' => [
                'label' => trim((string) ($criteria['location_label'] ?? '')) ?: 'Выбранная точка',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'starts_at' => $startsAt->toIso8601String(),
            ],
            'stops' => $stops->values()->all(),
            'geometry' => $routing['geometry'],
            'warnings' => array_values(array_unique($warnings)),
            'yandex_url' => $this->yandexRouteUrl($points, $transportMode),
        ];
    }

    private function candidateObjects(
        float $latitude,
        float $longitude,
        float $radiusKm,
        string $theme
    ): Collection {
        $latitudeDelta = $radiusKm / 111.0;
        $longitudeScale = max(0.2, cos(deg2rad($latitude)));
        $longitudeDelta = $radiusKm / (111.0 * $longitudeScale);

        $query = PilgrimageObject::query()
            ->published()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
            ->with([
                'objectType:id,name,slug,marker_color,icon',
                'sanctities' => fn ($query) => $query->where('slug', '<>', 'holy-spring'),
                'coverMedia',
                'deanery:id,name',
                'vicariate:id,name',
            ]);

        $this->applyTheme($query, $theme);

        return $query
            ->limit(350)
            ->get()
            ->map(function (PilgrimageObject $object) use ($latitude, $longitude): PilgrimageObject {
                $object->setAttribute('origin_distance_km', $this->distanceKm(
                    $latitude,
                    $longitude,
                    (float) $object->latitude,
                    (float) $object->longitude
                ));

                return $object;
            })
            ->filter(fn (PilgrimageObject $object): bool => (float) $object->origin_distance_km <= $radiusKm)
            ->sortBy(function (PilgrimageObject $object): float {
                $verificationBonus = $object->isInformationCurrent() ? -2.0 : 0.0;
                $schedulePenalty = filled($object->schedule_text) ? 0.0 : 4.0;
                $descriptionPenalty = filled($object->description) || filled($object->short_description) ? 0.0 : 2.0;

                return ((float) $object->origin_distance_km * 10)
                    + $schedulePenalty
                    + $descriptionPenalty
                    + $verificationBonus;
            })
            ->values();
    }

    private function applyTheme(Builder $query, string $theme): void
    {
        if ($theme === 'monasteries') {
            $query->whereHas('objectType', fn (Builder $query) => $query->where('slug', 'monastery'));
        } elseif ($theme === 'sanctities') {
            $query->whereHas('sanctities', fn (Builder $query) => $query->where('slug', '<>', 'holy-spring'));
        } elseif ($theme === 'icons') {
            $query->whereHas('sanctities', function (Builder $query): void {
                $query->where('slug', '<>', 'holy-spring')
                    ->where(function (Builder $query): void {
                        $query->where('name', 'like', '%икон%')
                            ->orWhere('type', 'like', '%икон%');
                    });
            });
        } elseif ($theme === 'relics') {
            $query->whereHas('sanctities', function (Builder $query): void {
                $query->where('slug', '<>', 'holy-spring')
                    ->where(function (Builder $query): void {
                        $query->where('name', 'like', '%мощ%')
                            ->orWhere('type', 'like', '%мощ%');
                    });
            });
        } elseif ($theme === 'history') {
            $query->whereNotNull('history')->whereRaw("TRIM(history) <> ''");
        } elseif ($theme === 'accessible') {
            $query->whereNotNull('accessibility_info')->whereRaw("TRIM(accessibility_info) <> ''");
        }
    }

    private function selectStops(
        Collection $candidates,
        float $startLatitude,
        float $startLongitude,
        Carbon $startsAt,
        int $availableMinutes,
        int $requestedCount,
        float $maxDistanceKm,
        string $transportMode,
        bool $allowUnknownSchedule
    ): array {
        $selected = collect();
        $remaining = $candidates->keyBy('id');
        $currentLatitude = $startLatitude;
        $currentLongitude = $startLongitude;
        $elapsedMinutes = 0;
        $distanceKm = 0.0;
        $warnings = [];

        while ($selected->count() < $requestedCount && $remaining->isNotEmpty()) {
            $evaluated = $remaining->map(function (PilgrimageObject $object) use (
                $currentLatitude,
                $currentLongitude,
                $startsAt,
                $elapsedMinutes,
                $distanceKm,
                $availableMinutes,
                $maxDistanceKm,
                $transportMode,
                $allowUnknownSchedule
            ): ?array {
                $straightDistance = $this->distanceKm(
                    $currentLatitude,
                    $currentLongitude,
                    (float) $object->latitude,
                    (float) $object->longitude
                );
                $legDistance = $straightDistance * $this->roadFactor($transportMode);
                $travelMinutes = $this->estimatedTravelMinutes($legDistance, $transportMode);
                $arrival = $startsAt->copy()->addMinutes($elapsedMinutes + $travelMinutes);
                $departure = $arrival->copy()->addMinutes(self::STAY_MINUTES);
                $availability = $this->scheduleAvailability($object->schedule_text, $arrival, $departure);

                if ($availability['status'] === 'closed') {
                    return null;
                }

                if ($availability['status'] === 'unknown' && ! $allowUnknownSchedule) {
                    return null;
                }

                if ($elapsedMinutes + $travelMinutes + self::STAY_MINUTES > $availableMinutes) {
                    return null;
                }

                if ($distanceKm + $legDistance > $maxDistanceKm) {
                    return null;
                }

                $schedulePenalty = $availability['status'] === 'unknown' ? 18.0 : 0.0;
                $verificationPenalty = $object->isInformationCurrent() ? 0.0 : 4.0;
                $informationPenalty = filled($object->schedule_text) ? 0.0 : 6.0;

                return [
                    'object' => $object,
                    'leg_distance_km' => $legDistance,
                    'travel_minutes' => $travelMinutes,
                    'availability' => $availability,
                    'rank' => ($legDistance * 10) + $schedulePenalty + $verificationPenalty + $informationPenalty,
                ];
            })->filter()->sortBy('rank');

            $best = $evaluated->first();
            if (! is_array($best)) {
                break;
            }

            /** @var PilgrimageObject $object */
            $object = $best['object'];
            $selected->push($object);
            $remaining->forget($object->id);
            $currentLatitude = (float) $object->latitude;
            $currentLongitude = (float) $object->longitude;
            $elapsedMinutes += (int) $best['travel_minutes'] + self::STAY_MINUTES;
            $distanceKm += (float) $best['leg_distance_km'];
        }

        if ($selected->isEmpty() && $candidates->isNotEmpty()) {
            $warnings[] = 'Поблизости есть объекты, но они не укладываются в выбранные ограничения по времени, расстоянию или расписанию.';
        }

        return [
            'stops' => $selected,
            'warnings' => $warnings,
        ];
    }

    private function routeMetrics(
        float $startLatitude,
        float $startLongitude,
        Collection $objects,
        string $transportMode
    ): array {
        if ($objects->isEmpty()) {
            return [
                'distance_km' => 0.0,
                'travel_minutes' => 0,
                'leg_minutes' => [],
                'geometry' => null,
                'exact' => false,
            ];
        }

        $locations = collect([[
            'lat' => $startLatitude,
            'lon' => $startLongitude,
            'type' => 'break',
        ]])->concat($objects->map(fn (PilgrimageObject $object): array => [
            'lat' => (float) $object->latitude,
            'lon' => (float) $object->longitude,
            'type' => 'break',
        ]))->values()->all();

        $baseUrl = rtrim((string) config('palomnik.maps.valhalla_url'), '/');

        if ($baseUrl !== '') {
            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->timeout((int) config('palomnik.maps.valhalla_timeout', 20))
                    ->retry(1, 250)
                    ->post($baseUrl.'/route', [
                        'locations' => $locations,
                        'costing' => $this->valhallaMode($transportMode),
                        'units' => 'kilometers',
                        'language' => 'ru-RU',
                        'format' => 'osrm',
                        'shape_format' => 'geojson',
                        'directions_options' => ['units' => 'kilometers'],
                    ]);

                $route = data_get($response->json(), 'routes.0');
                if ($response->successful() && is_array($route) && is_array($route['geometry'] ?? null)) {
                    $legMinutes = collect($route['legs'] ?? [])->map(
                        fn (array $leg): int => max(1, (int) ceil(((float) ($leg['duration'] ?? 0)) / 60))
                    )->values()->all();

                    return [
                        'distance_km' => ((float) ($route['distance'] ?? 0)) / 1000,
                        'travel_minutes' => max(1, (int) ceil(((float) ($route['duration'] ?? 0)) / 60)),
                        'leg_minutes' => $legMinutes,
                        'geometry' => $route['geometry'],
                        'exact' => true,
                    ];
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $distance = 0.0;
        $legMinutes = [];
        $previousLatitude = $startLatitude;
        $previousLongitude = $startLongitude;

        foreach ($objects as $object) {
            $legDistance = $this->distanceKm(
                $previousLatitude,
                $previousLongitude,
                (float) $object->latitude,
                (float) $object->longitude
            ) * $this->roadFactor($transportMode);
            $distance += $legDistance;
            $legMinutes[] = $this->estimatedTravelMinutes($legDistance, $transportMode);
            $previousLatitude = (float) $object->latitude;
            $previousLongitude = (float) $object->longitude;
        }

        return [
            'distance_km' => $distance,
            'travel_minutes' => array_sum($legMinutes),
            'leg_minutes' => $legMinutes,
            'geometry' => null,
            'exact' => false,
        ];
    }

    private function buildTimeline(
        Collection $objects,
        Carbon $startsAt,
        array $legMinutes,
        string $transportMode
    ): Collection {
        $elapsed = 0;

        return $objects->values()->map(function (PilgrimageObject $object, int $index) use (
            $startsAt,
            $legMinutes,
            $transportMode,
            &$elapsed
        ): array {
            $travelMinutes = (int) ($legMinutes[$index] ?? 0);
            if ($travelMinutes <= 0) {
                $travelMinutes = $this->estimatedTravelMinutes(
                    max(0.1, (float) ($object->origin_distance_km ?? 1.0)),
                    $transportMode
                );
            }

            $elapsed += $travelMinutes;
            $arrival = $startsAt->copy()->addMinutes($elapsed);
            $departure = $arrival->copy()->addMinutes(self::STAY_MINUTES);
            $availability = $this->scheduleAvailability($object->schedule_text, $arrival, $departure);
            $elapsed += self::STAY_MINUTES;

            return [
                'id' => $object->id,
                'slug' => $object->slug,
                'name' => $object->name,
                'address' => $object->address,
                'latitude' => (float) $object->latitude,
                'longitude' => (float) $object->longitude,
                'type' => optional($object->objectType)->name,
                'type_slug' => optional($object->objectType)->slug,
                'marker_color' => optional($object->objectType)->marker_color ?: '#b08a3e',
                'cover_url' => optional($object->coverMedia)->url,
                'url' => route('objects.show', $object),
                'deanery' => optional($object->deanery)->name,
                'vicariate' => optional($object->vicariate)->name,
                'short_description' => $object->short_description,
                'schedule_text' => $object->schedule_text,
                'schedule_status' => $availability['status'],
                'schedule_label' => $availability['label'],
                'arrival_at' => $arrival->toIso8601String(),
                'departure_at' => $departure->toIso8601String(),
                'travel_minutes' => $travelMinutes,
                'stay_minutes' => self::STAY_MINUTES,
                'information_current' => $object->isInformationCurrent(),
                'sanctities' => $object->sanctities->pluck('name')->values()->all(),
            ];
        });
    }

    private function scheduleAvailability(?string $schedule, Carbon $arrival, Carbon $departure): array
    {
        $schedule = trim((string) $schedule);
        if ($schedule === '') {
            return ['status' => 'unknown', 'label' => 'Расписание не указано'];
        }

        $normalized = mb_strtolower(str_replace("\r", '', $schedule));
        if (Str::contains($normalized, ['круглосуточно', '24 часа', '24/7'])) {
            return ['status' => 'open', 'label' => 'Доступно круглосуточно'];
        }

        $relevant = $this->relevantScheduleText($normalized, (int) $arrival->dayOfWeekIso);
        if ($relevant === '') {
            return ['status' => 'unknown', 'label' => 'Часы на выбранный день не определены'];
        }

        if (preg_match('/\b(выходн\w*|закрыт\w*|не\s+работает)\b/u', $relevant)) {
            return ['status' => 'closed', 'label' => 'На выбранное время указано закрытие'];
        }

        preg_match_all(
            '/(?<!\d)([01]?\d|2[0-3])(?:[:.]([0-5]\d))?\s*(?:-|–|—|до)\s*([01]?\d|2[0-3])(?:[:.]([0-5]\d))?/u',
            $relevant,
            $matches,
            PREG_SET_ORDER
        );

        if ($matches === []) {
            return ['status' => 'unknown', 'label' => 'Расписание требует уточнения'];
        }

        $arrivalMinutes = ((int) $arrival->format('H') * 60) + (int) $arrival->format('i');
        $departureMinutes = ((int) $departure->format('H') * 60) + (int) $departure->format('i');

        foreach ($matches as $match) {
            $from = ((int) $match[1] * 60) + (int) ($match[2] ?? 0);
            $to = ((int) $match[3] * 60) + (int) ($match[4] ?? 0);

            if ($to < $from) {
                $to += 24 * 60;
                if ($arrivalMinutes < $from) {
                    $arrivalMinutes += 24 * 60;
                    $departureMinutes += 24 * 60;
                }
            }

            if ($arrivalMinutes >= $from && $departureMinutes <= $to) {
                return [
                    'status' => 'open',
                    'label' => sprintf('Открыто в интервале %02d:%02d–%02d:%02d', (int) $match[1], (int) ($match[2] ?? 0), (int) $match[3], (int) ($match[4] ?? 0)),
                ];
            }
        }

        return ['status' => 'closed', 'label' => 'Предполагаемое посещение вне указанных часов'];
    }

    private function relevantScheduleText(string $schedule, int $dayOfWeekIso): string
    {
        $aliases = [
            1 => ['пн', 'понедельник'],
            2 => ['вт', 'вторник'],
            3 => ['ср', 'среда'],
            4 => ['чт', 'четверг'],
            5 => ['пт', 'пятница'],
            6 => ['сб', 'суббота'],
            7 => ['вс', 'воскресенье'],
        ];

        $lines = preg_split('/[\n;]+/u', $schedule) ?: [$schedule];
        $specificLines = [];
        $genericLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $hasDay = false;
            $applies = false;

            foreach ($aliases as $day => $tokens) {
                foreach ($tokens as $token) {
                    if (preg_match('/\b'.preg_quote($token, '/').'\b/u', $line)) {
                        $hasDay = true;
                        if ($day === $dayOfWeekIso) {
                            $applies = true;
                        }
                    }
                }
            }

            foreach ([[1, 5, 'пн', 'пт'], [1, 6, 'пн', 'сб'], [6, 7, 'сб', 'вс']] as $range) {
                if (preg_match('/\b'.$range[2].'\s*[-–—]\s*'.$range[3].'\b/u', $line)) {
                    $hasDay = true;
                    $applies = $dayOfWeekIso >= $range[0] && $dayOfWeekIso <= $range[1];
                }
            }

            if ($hasDay && $applies) {
                $specificLines[] = $line;
            } elseif (! $hasDay) {
                $genericLines[] = $line;
            }
        }

        if ($specificLines !== []) {
            return implode(' ', array_merge($specificLines, $genericLines));
        }

        return implode(' ', $genericLines ?: $lines);
    }

    private function routeTitle(Collection $stops, ?string $locationLabel): string
    {
        $deanery = $stops->pluck('deanery')->filter()->countBy()->sortDesc()->keys()->first();
        if ($deanery) {
            return 'Маршрут дня — '.$deanery;
        }

        $vicariate = $stops->pluck('vicariate')->filter()->countBy()->sortDesc()->keys()->first();
        if ($vicariate) {
            return 'Маршрут дня — '.$vicariate;
        }

        $locationLabel = trim((string) $locationLabel);
        if ($locationLabel !== '') {
            return 'Маршрут дня рядом с '.Str::limit($locationLabel, 80);
        }

        return 'Маршрут дня по храмам и монастырям';
    }

    private function yandexRouteUrl(Collection $points, string $transportMode): string
    {
        $coordinates = $points
            ->map(fn (array $point): string => $point['latitude'].','.$point['longitude'])
            ->implode('~');
        $routeType = [
            'walk' => 'pd',
            'public' => 'mt',
            'car' => 'auto',
        ][$transportMode] ?? 'pd';

        return 'https://yandex.ru/maps/?mode=routes&rtext='.rawurlencode($coordinates).'&rtt='.$routeType;
    }

    private function valhallaMode(string $transportMode): string
    {
        return [
            'walk' => 'pedestrian',
            'public' => 'multimodal',
            'car' => 'auto',
        ][$transportMode] ?? 'pedestrian';
    }

    private function roadFactor(string $transportMode): float
    {
        return [
            'walk' => 1.18,
            'public' => 1.35,
            'car' => 1.30,
        ][$transportMode] ?? 1.2;
    }

    private function estimatedTravelMinutes(float $distanceKm, string $transportMode): int
    {
        $speed = [
            'walk' => 4.5,
            'public' => 18.0,
            'car' => 28.0,
        ][$transportMode] ?? 4.5;
        $transfer = $transportMode === 'public' ? 7 : ($transportMode === 'car' ? 3 : 0);

        return max(1, (int) ceil(($distanceKm / $speed) * 60) + $transfer);
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $latitudeDelta = deg2rad($lat2 - $lat1);
        $longitudeDelta = deg2rad($lon2 - $lon1);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
