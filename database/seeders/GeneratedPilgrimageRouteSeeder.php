<?php

namespace Database\Seeders;

use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GeneratedPilgrimageRouteSeeder extends Seeder
{
    private const ROUTES_PER_SIZE = 50;

    private const ALLOWED_OBJECT_TYPES = [
        'temple',
        'monastery',
        'chapel',
        'holy-spring',
    ];

    private const EXCLUDED_NAME_PATTERN = '/('
        .'церковн\w*\s+лавк|иконн\w*\s+лавк|\bлавк\w*\b|'
        .'\bмагазин\w*\b|\bкиоск\w*\b|'
        .'\bпридел\w*\b|\bпредел\w*\b|'
        .'трапезн|просфорн|крестильн|'
        .'воскресн\w*\s+школ|приходск\w*\s+дом|дом\s+причта|'
        .'администрац|канцеляр|колокольн|звонниц'
        .')/iu';

    private const PROFILES = [
        'small' => [
            'label' => 'малый',
            'min_stops' => 3,
            'stop_variants' => 2,
            'radius_m' => 8000,
            'days_min' => 1,
            'days_variants' => 1,
            'difficulty' => 'easy',
            'category' => 'family',
            'speed_kmh' => 18,
            'min_minutes' => 150,
            'group_price' => 900,
            'spread_ratio' => 0.0,
        ],
        'medium' => [
            'label' => 'средний',
            'min_stops' => 5,
            'stop_variants' => 3,
            'radius_m' => 20000,
            'days_min' => 1,
            'days_variants' => 1,
            'difficulty' => 'medium',
            'category' => 'one_day',
            'speed_kmh' => 28,
            'min_minutes' => 330,
            'group_price' => 1800,
            'spread_ratio' => 0.15,
        ],
        'large' => [
            'label' => 'большой',
            'min_stops' => 9,
            'stop_variants' => 4,
            'radius_m' => 70000,
            'days_min' => 1,
            'days_variants' => 2,
            'difficulty' => 'medium',
            'category' => 'thematic',
            'speed_kmh' => 42,
            'min_minutes' => 600,
            'group_price' => 4900,
            'spread_ratio' => 0.65,
        ],
        'very_large' => [
            'label' => 'очень большой',
            'min_stops' => 16,
            'stop_variants' => 9,
            'radius_m' => 180000,
            'days_min' => 3,
            'days_variants' => 3,
            'difficulty' => 'hard',
            'category' => 'thematic',
            'speed_kmh' => 48,
            'min_minutes' => 1200,
            'group_price' => 9900,
            'spread_ratio' => 0.9,
        ],
    ];

    public function run(): void
    {
        $objects = $this->loadObjects();

        if ($objects->count() < 100) {
            throw new RuntimeException(
                'Для генерации 200 маршрутов требуется не менее 100 опубликованных '
                .'паломнических объектов с координатами. Сначала выполните импорт храмов.'
            );
        }

        $covers = $this->seedCovers();
        $moscow = $objects->filter(fn (PilgrimageObject $object) => $this->isMoscow($object))->values();
        $region = $objects->reject(fn (PilgrimageObject $object) => $this->isMoscow($object))->values();

        $usedAnchorIds = [];
        $created = 0;
        $updated = 0;
        $routeNumber = 0;

        DB::transaction(function () use (
            $objects,
            $moscow,
            $region,
            $covers,
            &$usedAnchorIds,
            &$created,
            &$updated,
            &$routeNumber
        ): void {
            foreach (self::PROFILES as $profileKey => $profile) {
                for ($localIndex = 0; $localIndex < self::ROUTES_PER_SIZE; $localIndex++) {
                    $routeNumber++;
                    $desiredStops = $profile['min_stops']
                        + ($localIndex % $profile['stop_variants']);
                    $durationDays = $profile['days_min']
                        + ($localIndex % $profile['days_variants']);

                    $preferredPool = $localIndex < 15 ? $moscow : $region;
                    if ($preferredPool->isEmpty()) {
                        $preferredPool = $objects;
                    }

                    $routeObjects = $this->findRouteObjects(
                        allObjects: $objects,
                        preferredAnchors: $preferredPool,
                        desiredStops: $desiredStops,
                        radiusM: $profile['radius_m'],
                        spreadRatio: $profile['spread_ratio'],
                        seed: $routeNumber,
                        usedAnchorIds: $usedAnchorIds,
                    );

                    if ($routeObjects->count() !== $desiredStops) {
                        throw new RuntimeException(
                            "Не удалось подобрать {$desiredStops} объектов для маршрута {$routeNumber}."
                        );
                    }

                    $metrics = $this->calculateMetrics(
                        $routeObjects,
                        $profile,
                        $durationDays,
                        $localIndex,
                    );
                    $anchor = $routeObjects->first();
                    $last = $routeObjects->last();
                    $slug = sprintf('generated-pilgrimage-route-%03d', $routeNumber);
                    $title = $this->makeTitle(
                        $profileKey,
                        $anchor,
                        $last,
                        $localIndex,
                    );
                    $isGroup = $this->isGroupRoute($profileKey, $localIndex);
                    $basePrice = $isGroup
                        ? $profile['group_price'] + ($desiredStops * 110) + ($durationDays * 250)
                        : 0;

                    $route = PilgrimageRoute::withTrashed()->where('slug', $slug)->first();
                    $wasExisting = (bool) $route;

                    $route = PilgrimageRoute::withTrashed()->updateOrCreate(
                        ['slug' => $slug],
                        [
                            'title' => $title,
                            'category' => $this->categoryFor(
                                $profileKey,
                                $localIndex,
                                $profile['category'],
                            ),
                            'difficulty' => $this->difficultyFor(
                                $profileKey,
                                $localIndex,
                                $profile['difficulty'],
                            ),
                            'duration_days' => $durationDays,
                            'duration_minutes' => $metrics['duration_minutes'],
                            'short_description' => $this->shortDescription(
                                $profileKey,
                                $routeObjects->count(),
                                $durationDays,
                                $metrics['distance_km'],
                            ),
                            'description' => $this->description(
                                $profileKey,
                                $routeObjects,
                                $durationDays,
                                $metrics['distance_km'],
                                $isGroup,
                            ),
                            'program' => $this->buildProgram(
                                $routeObjects,
                                $metrics['stay_minutes'],
                                $durationDays,
                            ),
                            'base_price' => $basePrice,
                            'is_group' => $isGroup,
                            'is_published' => true,
                            'published_at' => now()->subDays(($routeNumber % 30) + 1),
                            'cover_path' => $covers[$profileKey],
                        ]
                    );

                    if ($route->trashed()) {
                        $route->restore();
                    }

                    $route->objects()->sync(
                        $this->makePivotRows(
                            $routeObjects,
                            $metrics['stay_minutes'],
                        )
                    );

                    $wasExisting ? $updated++ : $created++;
                }
            }
        });

        $this->command?->info(
            "Маршруты созданы: новых {$created}, обновлено {$updated}, всего {$routeNumber}."
        );
        $this->command?->line(
            'Размеры: 50 малых, 50 средних, 50 больших и 50 очень больших маршрутов.'
        );
    }

    private function loadObjects(): Collection
    {
        $seen = [];

        return PilgrimageObject::query()
            ->with('objectType:id,slug')
            ->published()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '<>', 0)
            ->where('longitude', '<>', 0)
            ->whereHas('objectType', function ($query): void {
                $query->whereIn('slug', self::ALLOWED_OBJECT_TYPES);
            })
            ->orderBy('latitude')
            ->orderBy('longitude')
            ->get()
            ->filter(function (PilgrimageObject $object) use (&$seen): bool {
                if (preg_match(self::EXCLUDED_NAME_PATTERN, $object->name)) {
                    return false;
                }

                $key = mb_strtolower(trim($object->name))
                    .'|'.round((float) $object->latitude, 4)
                    .'|'.round((float) $object->longitude, 4);

                if (isset($seen[$key])) {
                    return false;
                }

                $seen[$key] = true;

                return true;
            })
            ->values();
    }

    private function findRouteObjects(
        Collection $allObjects,
        Collection $preferredAnchors,
        int $desiredStops,
        int $radiusM,
        float $spreadRatio,
        int $seed,
        array &$usedAnchorIds,
    ): Collection {
        $pools = [$preferredAnchors, $allObjects];

        foreach ($pools as $poolIndex => $pool) {
            if ($pool->isEmpty()) {
                continue;
            }

            $attempts = min(max($pool->count(), 1), 180);
            for ($attempt = 0; $attempt < $attempts; $attempt++) {
                $index = (($seed * 73) + ($attempt * 97) + ($poolIndex * 31)) % $pool->count();
                /** @var PilgrimageObject $anchor */
                $anchor = $pool->get($index);

                if (isset($usedAnchorIds[$anchor->id])) {
                    continue;
                }

                $selected = $this->selectNearbyObjects(
                    $allObjects,
                    $anchor,
                    $desiredStops,
                    $radiusM,
                    $spreadRatio,
                );

                if ($selected->count() === $desiredStops) {
                    $usedAnchorIds[$anchor->id] = true;

                    return $selected;
                }
            }
        }

        foreach ([1.35, 1.7, 2.2] as $multiplier) {
            $pool = $preferredAnchors->isNotEmpty() ? $preferredAnchors : $allObjects;
            for ($attempt = 0; $attempt < min($pool->count(), 200); $attempt++) {
                $index = (($seed * 41) + ($attempt * 59)) % $pool->count();
                /** @var PilgrimageObject $anchor */
                $anchor = $pool->get($index);
                $selected = $this->selectNearbyObjects(
                    $allObjects,
                    $anchor,
                    $desiredStops,
                    (int) round($radiusM * $multiplier),
                    $spreadRatio,
                );

                if ($selected->count() === $desiredStops) {
                    $usedAnchorIds[$anchor->id] = true;

                    return $selected;
                }
            }
        }

        return collect();
    }

    private function selectNearbyObjects(
        Collection $allObjects,
        PilgrimageObject $anchor,
        int $desiredStops,
        int $radiusM,
        float $spreadRatio,
    ): Collection {
        $nearby = $allObjects
            ->reject(fn (PilgrimageObject $object) => $object->id === $anchor->id)
            ->map(fn (PilgrimageObject $object) => [
                'object' => $object,
                'distance' => $this->distanceMeters($anchor, $object),
            ])
            ->filter(fn (array $row) => $row['distance'] <= $radiusM)
            ->sortBy('distance')
            ->pluck('object')
            ->values();

        if ($nearby->count() < $desiredStops - 1) {
            return collect();
        }

        if ($spreadRatio > 0 && $nearby->count() > $desiredStops - 1) {
            $spreadRatio = min(1.0, max(0.0, $spreadRatio));
            $maxIndex = max(0, (int) floor(($nearby->count() - 1) * $spreadRatio));
            $chosen = collect();

            for ($step = 1; $step < $desiredStops; $step++) {
                $index = (int) round(
                    $maxIndex * ($step / max(1, $desiredStops - 1))
                );
                $candidate = $nearby->get($index);
                if ($candidate && ! $chosen->contains('id', $candidate->id)) {
                    $chosen->push($candidate);
                }
            }

            foreach ($nearby as $candidate) {
                if ($chosen->count() >= $desiredStops - 1) {
                    break;
                }
                if (! $chosen->contains('id', $candidate->id)) {
                    $chosen->push($candidate);
                }
            }

            $nearby = $chosen->values();
        } else {
            $nearby = $nearby->take($desiredStops - 1)->values();
        }

        $remaining = $nearby->keyBy('id');
        $ordered = collect([$anchor]);
        $current = $anchor;

        while ($ordered->count() < $desiredStops && $remaining->isNotEmpty()) {
            /** @var PilgrimageObject|null $next */
            $next = $remaining
                ->sortBy(fn (PilgrimageObject $object) => $this->distanceMeters($current, $object))
                ->first();

            if (! $next) {
                break;
            }

            $ordered->push($next);
            $remaining->forget($next->id);
            $current = $next;
        }

        return $ordered->values();
    }

    private function calculateMetrics(
        Collection $objects,
        array $profile,
        int $durationDays,
        int $variant,
    ): array {
        $distanceM = 0.0;
        $stayMinutes = [];
        $previous = null;

        foreach ($objects as $index => $object) {
            if ($previous) {
                $distanceM += $this->distanceMeters($previous, $object);
            }

            $type = $object->objectType?->slug;
            $stayMinutes[$object->id] = match ($type) {
                'monastery' => 80 + (($variant + $index) % 3) * 10,
                'holy-spring' => 50 + (($variant + $index) % 2) * 10,
                'chapel' => 25 + (($variant + $index) % 2) * 5,
                default => 40 + (($variant + $index) % 3) * 10,
            };
            $previous = $object;
        }

        $visitMinutes = array_sum($stayMinutes);
        $travelMinutes = (int) round(($distanceM / 1000) / $profile['speed_kmh'] * 60);
        $breakMinutes = 45 * $durationDays;
        $durationMinutes = max(
            $profile['min_minutes'],
            $visitMinutes + $travelMinutes + $breakMinutes,
        );

        return [
            'distance_km' => round($distanceM / 1000, 1),
            'duration_minutes' => $durationMinutes,
            'stay_minutes' => $stayMinutes,
        ];
    }

    private function makeTitle(
        string $profileKey,
        PilgrimageObject $anchor,
        PilgrimageObject $last,
        int $variant,
    ): string {
        $templates = match ($profileKey) {
            'small' => [
                'Малый круг святынь',
                'Тихая паломническая прогулка',
                'Храмы рядом',
                'Утро среди святынь',
                'Небольшой путь паломника',
            ],
            'medium' => [
                'Паломнический день',
                'Святыни одного дня',
                'Дорога к храмам',
                'Храмы и обители рядом',
                'Дневной маршрут паломника',
            ],
            'large' => [
                'Большой круг святынь',
                'Большое паломничество одного края',
                'Храмы и монастыри большого пути',
                'Пространный маршрут по святыням',
                'Большая дорога паломника',
            ],
            default => [
                'Многодневный путь по святыням',
                'Очень большое паломничество',
                'Дорога через храмы и обители',
                'Большой путь по Московской земле',
                'Несколько дней среди святынь',
            ],
        };

        $prefix = $templates[$variant % count($templates)];
        $anchorName = Str::limit($anchor->name, 72, '…');
        $lastName = Str::limit($last->name, 58, '…');

        return Str::limit("{$prefix}: {$anchorName} — {$lastName}", 250, '…');
    }

    private function shortDescription(
        string $profileKey,
        int $stops,
        int $days,
        float $distanceKm,
    ): string {
        $size = self::PROFILES[$profileKey]['label'];

        return ucfirst($size)
            ." маршрут на {$days} дн.: {$stops} остановок, примерно {$distanceKm} км. "
            .'Точки подобраны по близости друг к другу из действующего каталога.';
    }

    private function description(
        string $profileKey,
        Collection $objects,
        int $days,
        float $distanceKm,
        bool $isGroup,
    ): string {
        /** @var PilgrimageObject $first */
        $first = $objects->first();
        /** @var PilgrimageObject $last */
        $last = $objects->last();
        $format = $isGroup
            ? 'Маршрут рассчитан как организованная групповая поездка.'
            : 'Маршрут можно пройти самостоятельно в удобном темпе.';
        $transport = match ($profileKey) {
            'small' => 'Основной способ передвижения — пешком и на городском транспорте.',
            'medium' => 'Подойдёт сочетание пешего перехода, общественного транспорта и автомобиля.',
            'large' => 'Рекомендуется автомобиль или автобус; маршрут допускает разделение на два дня.',
            default => 'Нужен автомобиль или автобус и предварительное планирование ночёвок.',
        };

        return "Начальная точка — «{$first->name}», завершающая — «{$last->name}». "
            ."В маршрут включено {$objects->count()} опубликованных объектов каталога, "
            ."ориентировочная протяжённость — {$distanceKm} км, продолжительность — {$days} дн. "
            .$format.' '.$transport.' '
            .'Перед поездкой необходимо проверить актуальное расписание богослужений, '
            .'режим посещения территорий, состояние дорог и доступность парковок.';
    }

    private function buildProgram(
        Collection $objects,
        array $stayMinutes,
        int $durationDays,
    ): string {
        $lines = [];
        $chunks = $objects->chunk((int) ceil($objects->count() / $durationDays));

        foreach ($chunks as $dayIndex => $dayObjects) {
            if ($durationDays > 1) {
                $lines[] = 'День '.($dayIndex + 1);
            }

            $clock = 8 * 60 + 30;
            foreach ($dayObjects->values() as $index => $object) {
                $lines[] = $this->formatClock($clock).' — '.$object->name.'.';
                $clock += $stayMinutes[$object->id] ?? 45;

                $next = $dayObjects->values()->get($index + 1);
                if ($next) {
                    $distance = $this->distanceMeters($object, $next);
                    $clock += max(10, (int) round(($distance / 1000) / 30 * 60));
                }
            }

            $lines[] = $this->formatClock($clock).' — завершение программы дня.';
            if ($dayIndex < $chunks->count() - 1) {
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    private function makePivotRows(Collection $objects, array $stayMinutes): array
    {
        $sync = [];
        $previous = null;

        foreach ($objects as $index => $object) {
            $distanceText = $previous
                ? ' От предыдущей точки — '
                    .round($this->distanceMeters($previous, $object) / 1000, 1).' км.'
                : ' Начальная точка маршрута.';

            $sync[$object->id] = [
                'sort_order' => $index + 1,
                'stay_minutes' => $stayMinutes[$object->id] ?? 45,
                'note' => 'Рекомендуемая остановка.'.$distanceText,
            ];
            $previous = $object;
        }

        return $sync;
    }

    private function categoryFor(string $profileKey, int $variant, string $default): string
    {
        if ($profileKey === 'small') {
            return $variant % 3 === 0 ? 'family' : 'one_day';
        }

        if ($profileKey === 'medium') {
            return $variant % 4 === 0 ? 'family' : 'one_day';
        }

        return $default;
    }

    private function difficultyFor(string $profileKey, int $variant, string $default): string
    {
        if ($profileKey === 'large' && $variant % 3 === 0) {
            return 'hard';
        }

        if ($profileKey === 'medium' && $variant % 5 === 0) {
            return 'easy';
        }

        return $default;
    }

    private function isGroupRoute(string $profileKey, int $variant): bool
    {
        return match ($profileKey) {
            'small' => $variant % 4 === 0,
            'medium' => $variant % 2 === 0,
            'large' => $variant % 4 !== 0,
            default => true,
        };
    }

    private function isMoscow(PilgrimageObject $object): bool
    {
        $address = mb_strtolower(trim((string) $object->address));

        return str_starts_with($address, 'москва,')
            || str_starts_with($address, 'г. москва')
            || str_starts_with($address, 'город москва');
    }

    private function distanceMeters(
        PilgrimageObject $a,
        PilgrimageObject $b,
    ): float {
        $earthRadiusM = 6371000.0;
        $lat1 = deg2rad((float) $a->latitude);
        $lat2 = deg2rad((float) $b->latitude);
        $deltaLat = $lat2 - $lat1;
        $deltaLon = deg2rad((float) $b->longitude - (float) $a->longitude);

        $h = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
        $h = min(1.0, max(0.0, $h));

        return $earthRadiusM * 2 * atan2(sqrt($h), sqrt(1 - $h));
    }

    private function formatClock(int $minutes): string
    {
        $minutes %= 24 * 60;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function seedCovers(): array
    {
        Storage::disk('public')->makeDirectory('generated-routes');

        $rows = [
            'small' => ['Малые маршруты', '3–4 святыни', '#315b4f', '#c7a45a'],
            'medium' => ['Средние маршруты', '5–7 остановок', '#31556e', '#d3b36e'],
            'large' => ['Большие маршруты', '9–12 остановок', '#65453b', '#d0a45d'],
            'very_large' => ['Очень большие маршруты', '16–24 остановки', '#433d63', '#c9a966'],
        ];

        $paths = [];
        foreach ($rows as $key => [$title, $subtitle, $from, $to]) {
            $path = "generated-routes/{$key}.svg";
            Storage::disk('public')->put(
                $path,
                $this->coverSvg($title, $subtitle, $from, $to),
            );
            $paths[$key] = $path;
        }

        return $paths;
    }

    private function coverSvg(
        string $title,
        string $subtitle,
        string $from,
        string $to,
    ): string {
        $title = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $subtitle = htmlspecialchars($subtitle, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900" viewBox="0 0 1600 900">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$from}"/>
      <stop offset="1" stop-color="{$to}"/>
    </linearGradient>
  </defs>
  <rect width="1600" height="900" fill="url(#bg)"/>
  <circle cx="1240" cy="210" r="270" fill="#ffffff" opacity="0.08"/>
  <circle cx="1380" cy="730" r="390" fill="#ffffff" opacity="0.06"/>
  <path d="M190 650 C420 450 610 610 820 390 S1210 250 1410 410" fill="none" stroke="#fff" stroke-width="14" opacity="0.65"/>
  <g fill="#fff">
    <circle cx="190" cy="650" r="25"/>
    <circle cx="820" cy="390" r="25"/>
    <circle cx="1410" cy="410" r="25"/>
  </g>
  <text x="120" y="210" fill="#fff" font-family="Arial, sans-serif" font-size="76" font-weight="700">{$title}</text>
  <text x="124" y="292" fill="#fff" opacity="0.88" font-family="Arial, sans-serif" font-size="42">{$subtitle}</text>
  <text x="124" y="800" fill="#fff" opacity="0.75" font-family="Arial, sans-serif" font-size="30">Московский паломник</text>
</svg>
SVG;
    }
}
