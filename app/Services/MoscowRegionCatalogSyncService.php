<?php

namespace App\Services;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\PointOfInterest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class MoscowRegionCatalogSyncService
{
    private const ALLOWED_TYPES = ['temple', 'monastery', 'chapel'];
    private const ALLOWED_POI_CATEGORIES = ['parking', 'cafe', 'hotel'];
    private const GENERATED_POI_MARKER = 'Данные OpenStreetMap';
    private const MINIMUM_FULL_SNAPSHOT_OBJECTS = 100;

    private PilgrimageObjectMergeService $mergeService;
    private AdminActivityLogger $activityLogger;

    public function __construct(
        PilgrimageObjectMergeService $mergeService,
        AdminActivityLogger $activityLogger
    ) {
        $this->mergeService = $mergeService;
        $this->activityLogger = $activityLogger;
    }

    /**
     * @throws JsonException
     */
    public function sync(string $objectsPath, string $nearbyPath, bool $clean = true): array
    {
        $objectsPath = $this->resolvePath($objectsPath);
        $nearbyPath = $this->resolvePath($nearbyPath);
        $objectsSnapshot = $this->readSnapshot($objectsPath, 'objects');
        $nearbySnapshot = $this->readSnapshot($nearbyPath, 'points');

        $prepared = $this->prepareObjects($objectsSnapshot['objects']);

        if ($clean && count($prepared['objects']) < self::MINIMUM_FULL_SNAPSHOT_OBJECTS) {
            throw new RuntimeException(
                'Очистка отменена: после проверки в JSON осталось только '
                .count($prepared['objects']).' объектов. Для полной синхронизации требуется не менее '
                .self::MINIMUM_FULL_SNAPSHOT_OBJECTS.'. Используйте --no-clean для частичного импорта.'
            );
        }

        $objectResult = $this->syncObjects(
            $prepared['objects'],
            $prepared['slug_map'],
            $clean
        );

        $pointResult = $this->syncNearbyPoints(
            $nearbySnapshot['points'],
            $prepared['slug_map'],
            $clean
        );

        $batchId = 'moscow-catalog-sync-'.now()->format('YmdHis');
        $result = [
            'batch_id' => $batchId,
            'objects_file' => $objectsPath,
            'nearby_file' => $nearbyPath,
            'objects_file_sha256' => hash_file('sha256', $objectsPath),
            'nearby_file_sha256' => hash_file('sha256', $nearbyPath),
            'clean_enabled' => $clean,
            'source_objects' => count($objectsSnapshot['objects']),
            'source_points' => count($nearbySnapshot['points']),
            'prepared' => $prepared['stats'],
            'objects' => $objectResult,
            'points' => $pointResult,
        ];

        $this->activityLogger->log(
            'import',
            null,
            null,
            null,
            $result,
            null,
            'import',
            $batchId,
            PilgrimageObject::class,
            null,
            'Полная синхронизация храмов и инфраструктуры Москвы и Подмосковья'
        );

        return $result;
    }

    private function resolvePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new RuntimeException('Не указан путь к JSON-файлу.');
        }

        $candidates = [$path];
        if (! $this->isAbsolutePath($path)) {
            $candidates[] = base_path($path);
            $candidates[] = database_path('seeders/data/'.$path);
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $resolved = realpath($candidate);

                return $resolved !== false ? $resolved : $candidate;
            }
        }

        throw new RuntimeException('JSON-файл не найден или недоступен для чтения: '.$path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    /**
     * @throws JsonException
     */
    private function readSnapshot(string $path, string $arrayKey): array
    {
        $snapshot = json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (! is_array($snapshot) || ! isset($snapshot[$arrayKey]) || ! is_array($snapshot[$arrayKey])) {
            throw new RuntimeException(
                'Некорректный JSON '.$path.': отсутствует массив '.$arrayKey.'.'
            );
        }

        return $snapshot;
    }

    private function prepareObjects(array $items): array
    {
        $bySlug = [];
        $stats = [
            'invalid' => 0,
            'auxiliary_removed' => 0,
            'same_source_merged' => 0,
            'nearby_duplicates_merged' => 0,
        ];

        foreach ($items as $item) {
            $normalized = $this->normalizeObjectItem($item);
            if ($normalized === null) {
                $stats['invalid']++;
                continue;
            }

            if ($this->isAuxiliaryObjectName($normalized['name'])) {
                $stats['auxiliary_removed']++;
                continue;
            }

            $slug = $normalized['slug'];
            if (! isset($bySlug[$slug])) {
                $bySlug[$slug] = $normalized;
                continue;
            }

            $bySlug[$slug] = $this->mergeIncomingObject($bySlug[$slug], $normalized);
            $stats['same_source_merged']++;
        }

        uasort($bySlug, function (array $first, array $second): int {
            return $this->objectRichness($second) <=> $this->objectRichness($first);
        });

        $result = [];
        $slugMap = [];
        $spatialBuckets = [];

        foreach ($bySlug as $slug => $item) {
            $duplicateSlug = $this->findPreparedDuplicate($item, $result, $spatialBuckets);

            if ($duplicateSlug !== null) {
                $result[$duplicateSlug] = $this->mergeIncomingObject($result[$duplicateSlug], $item);
                $slugMap[$slug] = $duplicateSlug;
                $stats['nearby_duplicates_merged']++;
                continue;
            }

            $result[$slug] = $item;
            $cell = $this->spatialCell((float) $item['latitude'], (float) $item['longitude']);
            $spatialBuckets[$cell][] = $slug;
        }

        foreach (array_keys($bySlug) as $slug) {
            $slugMap[$slug] = $this->resolveMappedSlug($slug, $slugMap);
        }

        return [
            'objects' => $result,
            'slug_map' => $slugMap,
            'stats' => $stats + ['ready' => count($result)],
        ];
    }

    private function normalizeObjectItem(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $slug = trim((string) ($item['slug'] ?? ''));
        $name = trim((string) ($item['name'] ?? ''));
        $type = trim((string) ($item['type'] ?? ''));
        $latitude = (float) ($item['latitude'] ?? 0);
        $longitude = (float) ($item['longitude'] ?? 0);

        if (
            $slug === ''
            || $name === ''
            || ! str_starts_with($slug, 'osm-')
            || ! in_array($type, self::ALLOWED_TYPES, true)
            || $latitude === 0.0
            || $longitude === 0.0
        ) {
            return null;
        }

        return [
            'slug' => $slug,
            'type' => $type,
            'name' => $name,
            'address' => $this->nullableString($item['address'] ?? null) ?: 'Адрес уточняется',
            'latitude' => round($latitude, 7),
            'longitude' => round($longitude, 7),
            'phone' => $this->nullableString($item['phone'] ?? null),
            'email' => $this->nullableString($item['email'] ?? null),
            'website' => $this->nullableString($item['website'] ?? null),
            'schedule_text' => $this->nullableString($item['schedule_text'] ?? null),
            'short_description' => $this->nullableString($item['short_description'] ?? null),
            'description' => $this->nullableString($item['description'] ?? null),
            'history' => $this->nullableString($item['history'] ?? null),
            'source_url' => $this->nullableString($item['source_url'] ?? null),
            'source_id' => $this->nullableString($item['source_id'] ?? null),
            'osm_type' => $this->nullableString($item['osm_type'] ?? null),
        ];
    }

    private function findPreparedDuplicate(
        array $item,
        array $prepared,
        array $spatialBuckets
    ): ?string {
        [$latCell, $lngCell] = $this->spatialCellParts(
            (float) $item['latitude'],
            (float) $item['longitude']
        );

        for ($latOffset = -1; $latOffset <= 1; $latOffset++) {
            for ($lngOffset = -1; $lngOffset <= 1; $lngOffset++) {
                $cell = ($latCell + $latOffset).':'.($lngCell + $lngOffset);

                foreach ($spatialBuckets[$cell] ?? [] as $candidateSlug) {
                    $candidate = $prepared[$candidateSlug] ?? null;
                    if ($candidate !== null && $this->objectsAreDuplicates($candidate, $item)) {
                        return $candidateSlug;
                    }
                }
            }
        }

        return null;
    }

    private function objectsAreDuplicates(array $first, array $second): bool
    {
        $distance = $this->distanceMeters(
            (float) $first['latitude'],
            (float) $first['longitude'],
            (float) $second['latitude'],
            (float) $second['longitude']
        );

        if ($distance > 100) {
            return false;
        }

        $nameA = $this->canonicalObjectName($first['name']);
        $nameB = $this->canonicalObjectName($second['name']);
        $similarity = $this->nameSimilarity($nameA, $nameB);
        $sameCanonicalName = $nameA !== '' && $nameA === $nameB;
        $samePhone = $this->normalizePhone($first['phone']) !== null
            && $this->normalizePhone($first['phone']) === $this->normalizePhone($second['phone']);
        $sameWebsite = $this->normalizeWebsite($first['website']) !== null
            && $this->normalizeWebsite($first['website']) === $this->normalizeWebsite($second['website']);

        if ($sameCanonicalName && $distance <= 80) {
            return true;
        }

        if ($distance <= 35 && $similarity >= 90) {
            return true;
        }

        if ($distance <= 15 && $similarity >= 75) {
            return true;
        }

        return ($samePhone || $sameWebsite) && $distance <= 60 && $similarity >= 70;
    }

    private function mergeIncomingObject(array $master, array $duplicate): array
    {
        foreach ([
            'phone',
            'email',
            'website',
            'schedule_text',
            'short_description',
            'description',
            'history',
            'source_url',
            'source_id',
            'osm_type',
        ] as $field) {
            if (blank($master[$field] ?? null) && filled($duplicate[$field] ?? null)) {
                $master[$field] = $duplicate[$field];
            }
        }

        if (($master['address'] ?? '') === 'Адрес уточняется'
            && ($duplicate['address'] ?? '') !== 'Адрес уточняется') {
            $master['address'] = $duplicate['address'];
        }

        return $master;
    }

    private function syncObjects(array $items, array $slugMap, bool $clean): array
    {
        $typeIds = ObjectType::query()
            ->visible()
            ->whereIn('slug', self::ALLOWED_TYPES)
            ->pluck('id', 'slug');

        foreach (self::ALLOWED_TYPES as $type) {
            if (! $typeIds->has($type)) {
                throw new RuntimeException(
                    'Не найден активный публичный тип '.$type.'. Выполните CatalogSeeder.'
                );
            }
        }

        $stats = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'trashed_kept' => 0,
            'duplicates_merged' => 0,
            'auxiliary_merged' => 0,
            'auxiliary_archived' => 0,
            'stale_archived' => 0,
        ];

        DB::transaction(function () use ($items, $typeIds, &$stats): void {
            foreach ($items as $slug => $item) {
                $object = PilgrimageObject::withTrashed()->where('slug', $slug)->first();

                if ($object?->trashed()) {
                    $stats['trashed_kept']++;
                    continue;
                }

                $incoming = $this->objectDatabasePayload($item, (int) $typeIds[$item['type']]);

                if (! $object) {
                    PilgrimageObject::query()->create($incoming + [
                        'slug' => $slug,
                        'is_published' => true,
                        'published_at' => now(),
                        'verification_status' => PilgrimageObject::VERIFICATION_NEEDS_REVIEW,
                    ]);
                    $stats['created']++;
                    continue;
                }

                $updates = $this->changedObjectFields($object, $incoming);
                if ($updates === []) {
                    $stats['unchanged']++;
                    continue;
                }

                if ($object->verification_status === PilgrimageObject::VERIFICATION_VERIFIED) {
                    $updates['verification_status'] = PilgrimageObject::VERIFICATION_NEEDS_REVIEW;
                }

                $object->update($updates);
                $stats['updated']++;
            }
        });

        foreach ($slugMap as $duplicateSlug => $masterSlug) {
            if ($duplicateSlug === $masterSlug || isset($items[$duplicateSlug])) {
                continue;
            }

            $duplicate = PilgrimageObject::query()->where('slug', $duplicateSlug)->first();
            $master = PilgrimageObject::query()->where('slug', $masterSlug)->first();

            if (! $duplicate || ! $master || $duplicate->id === $master->id) {
                continue;
            }

            $this->mergeService->merge($master, $duplicate);
            $stats['duplicates_merged']++;
        }

        if ($clean) {
            $keepSlugs = array_keys($items);

            PilgrimageObject::query()
                ->where('slug', 'like', 'osm-%')
                ->whereNotIn('slug', $keepSlugs)
                ->orderBy('id')
                ->chunkById(200, function ($objects) use (&$stats): void {
                    foreach ($objects as $object) {
                        if ($this->isAuxiliaryObjectName($object->name)) {
                            $parent = $this->findLikelyParentTemple($object);
                            if ($parent) {
                                $this->mergeService->merge($parent, $object);
                                $stats['auxiliary_merged']++;
                            } else {
                                $object->delete();
                                $stats['auxiliary_archived']++;
                            }
                            continue;
                        }

                        $object->delete();
                        $stats['stale_archived']++;
                    }
                });
        }

        return $stats;
    }

    private function objectDatabasePayload(array $item, int $typeId): array
    {
        return [
            'object_type_id' => $typeId,
            'name' => $item['name'],
            'address' => $item['address'],
            'latitude' => $item['latitude'],
            'longitude' => $item['longitude'],
            'phone' => $item['phone'],
            'email' => $item['email'],
            'website' => $item['website'],
            'schedule_text' => $item['schedule_text'],
            'short_description' => $item['short_description'],
            'description' => $item['description'],
            'history' => $item['history'],
            'information_source_url' => $item['source_url'],
        ];
    }

    private function changedObjectFields(PilgrimageObject $object, array $incoming): array
    {
        $alwaysUpdate = [
            'object_type_id',
            'name',
            'address',
            'latitude',
            'longitude',
        ];
        $fillOrUpdateWhenIncomingPresent = [
            'phone',
            'email',
            'website',
            'schedule_text',
            'information_source_url',
        ];
        $editorialOnlyWhenBlank = [
            'short_description',
            'description',
            'history',
        ];
        $updates = [];

        foreach ($alwaysUpdate as $field) {
            if ((string) $object->{$field} !== (string) $incoming[$field]) {
                $updates[$field] = $incoming[$field];
            }
        }

        foreach ($fillOrUpdateWhenIncomingPresent as $field) {
            if (filled($incoming[$field]) && (string) $object->{$field} !== (string) $incoming[$field]) {
                $updates[$field] = $incoming[$field];
            }
        }

        foreach ($editorialOnlyWhenBlank as $field) {
            if (blank($object->{$field}) && filled($incoming[$field])) {
                $updates[$field] = $incoming[$field];
            }
        }

        return $updates;
    }

    private function findLikelyParentTemple(PilgrimageObject $auxiliary): ?PilgrimageObject
    {
        if (! is_numeric($auxiliary->latitude) || ! is_numeric($auxiliary->longitude)) {
            return null;
        }

        $latitude = (float) $auxiliary->latitude;
        $longitude = (float) $auxiliary->longitude;
        $latDelta = 0.0015;
        $lngDelta = 0.0025;
        $auxiliaryAddress = $this->normalizeAddress($auxiliary->address);
        $auxiliaryPhone = $this->normalizePhone($auxiliary->phone);
        $auxiliaryWebsite = $this->normalizeWebsite($auxiliary->website);

        $candidates = PilgrimageObject::query()
            ->where('id', '<>', $auxiliary->id)
            ->where('slug', 'like', 'osm-%')
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
            ->whereHas('objectType', fn ($query) => $query->whereIn('slug', ['temple', 'monastery']))
            ->get();

        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            if ($this->isAuxiliaryObjectName($candidate->name)) {
                continue;
            }

            $distance = $this->distanceMeters(
                $latitude,
                $longitude,
                (float) $candidate->latitude,
                (float) $candidate->longitude
            );
            if ($distance > 150) {
                continue;
            }

            $sameAddress = $auxiliaryAddress !== ''
                && $auxiliaryAddress === $this->normalizeAddress($candidate->address);
            $samePhone = $auxiliaryPhone !== null
                && $auxiliaryPhone === $this->normalizePhone($candidate->phone);
            $sameWebsite = $auxiliaryWebsite !== null
                && $auxiliaryWebsite === $this->normalizeWebsite($candidate->website);

            if (! $sameAddress && ! $samePhone && ! $sameWebsite && $distance > 35) {
                continue;
            }

            if ($distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    private function syncNearbyPoints(array $items, array $slugMap, bool $clean): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'trashed_kept' => 0,
            'invalid' => 0,
            'missing_object' => 0,
            'input_duplicates' => 0,
            'stale_archived' => 0,
        ];
        $objectCache = [];
        $prepared = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                $stats['invalid']++;
                continue;
            }

            $originalSlug = trim((string) ($item['object_slug'] ?? ''));
            $objectSlug = $this->resolveMappedSlug($originalSlug, $slugMap);
            $category = trim((string) ($item['category'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));
            $latitude = (float) ($item['latitude'] ?? 0);
            $longitude = (float) ($item['longitude'] ?? 0);

            if (
                $objectSlug === ''
                || $name === ''
                || ! in_array($category, self::ALLOWED_POI_CATEGORIES, true)
                || $latitude === 0.0
                || $longitude === 0.0
            ) {
                $stats['invalid']++;
                continue;
            }

            if (! array_key_exists($objectSlug, $objectCache)) {
                $objectCache[$objectSlug] = PilgrimageObject::query()
                    ->where('slug', $objectSlug)
                    ->first();
            }

            $object = $objectCache[$objectSlug];
            if (! $object) {
                $stats['missing_object']++;
                continue;
            }

            $key = $this->pointKey($object->id, $category, $latitude, $longitude);
            $incoming = [
                'pilgrimage_object_id' => $object->id,
                'category' => $category,
                'name' => $name,
                'description' => $this->nullableString($item['description'] ?? null)
                    ?: 'Данные OpenStreetMap.',
                'address' => $this->nullableString($item['address'] ?? null),
                'latitude' => round($latitude, 7),
                'longitude' => round($longitude, 7),
                'phone' => $this->nullableString($item['phone'] ?? null),
                'website' => $this->nullableString($item['website'] ?? null),
                'schedule_text' => $this->nullableString($item['schedule_text'] ?? null),
                'is_published' => true,
                'sort_order' => max(0, (int) ($item['sort_order'] ?? 0)),
            ];

            if (isset($prepared[$key])) {
                $prepared[$key] = $this->mergeIncomingPoint($prepared[$key], $incoming);
                $stats['input_duplicates']++;
            } else {
                $prepared[$key] = $incoming;
            }
        }

        DB::transaction(function () use ($prepared, &$stats): void {
            foreach ($prepared as $incoming) {
                $point = PointOfInterest::withTrashed()
                    ->where('pilgrimage_object_id', $incoming['pilgrimage_object_id'])
                    ->where('category', $incoming['category'])
                    ->where('latitude', $incoming['latitude'])
                    ->where('longitude', $incoming['longitude'])
                    ->first();

                if ($point?->trashed()) {
                    $stats['trashed_kept']++;
                    continue;
                }

                if (! $point) {
                    PointOfInterest::query()->create($incoming);
                    $stats['created']++;
                    continue;
                }

                $changes = [];
                foreach ($incoming as $field => $value) {
                    if ((string) $point->{$field} !== (string) $value) {
                        $changes[$field] = $value;
                    }
                }

                if ($changes === []) {
                    $stats['unchanged']++;
                } else {
                    $point->update($changes);
                    $stats['updated']++;
                }
            }
        });

        if ($clean) {
            PointOfInterest::query()
                ->whereIn('category', self::ALLOWED_POI_CATEGORIES)
                ->where('description', 'like', '%'.self::GENERATED_POI_MARKER.'%')
                ->whereHas('pilgrimageObject', fn ($query) => $query->where('slug', 'like', 'osm-%'))
                ->with('pilgrimageObject:id,slug')
                ->orderBy('id')
                ->chunkById(500, function ($points) use ($prepared, &$stats): void {
                    foreach ($points as $point) {
                        $key = $this->pointKey(
                            (int) $point->pilgrimage_object_id,
                            (string) $point->category,
                            (float) $point->latitude,
                            (float) $point->longitude
                        );

                        if (! isset($prepared[$key])) {
                            $point->delete();
                            $stats['stale_archived']++;
                        }
                    }
                });
        }

        return $stats;
    }

    private function mergeIncomingPoint(array $master, array $duplicate): array
    {
        foreach (['description', 'address', 'phone', 'website', 'schedule_text'] as $field) {
            if (blank($master[$field] ?? null) && filled($duplicate[$field] ?? null)) {
                $master[$field] = $duplicate[$field];
            }
        }

        $master['sort_order'] = min((int) $master['sort_order'], (int) $duplicate['sort_order']);

        return $master;
    }

    private function pointKey(int $objectId, string $category, float $latitude, float $longitude): string
    {
        return $objectId.'|'.$category.'|'.number_format($latitude, 7, '.', '')
            .'|'.number_format($longitude, 7, '.', '');
    }

    private function isAuxiliaryObjectName(?string $name): bool
    {
        return preg_match('/\b(?:придел|предел)\w*/ui', (string) $name) === 1;
    }

    private function objectRichness(array $item): int
    {
        $score = 0;
        foreach ([
            'address',
            'phone',
            'email',
            'website',
            'schedule_text',
            'short_description',
            'description',
            'history',
            'source_url',
        ] as $field) {
            if (filled($item[$field] ?? null)) {
                $score++;
            }
        }

        if (($item['address'] ?? '') !== 'Адрес уточняется') {
            $score += 2;
        }
        if (($item['osm_type'] ?? null) !== 'node') {
            $score += 2;
        }

        return $score;
    }

    private function canonicalObjectName(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/[^a-zа-я0-9]+/ui', ' ', $value) ?: '';
        $stopWords = [
            'храм', 'церковь', 'церкви', 'собор', 'часовня', 'монастырь',
            'православный', 'православная', 'приход', 'во', 'в', 'имя', 'честь',
            'святого', 'святой', 'святая', 'святых', 'иконы', 'божией', 'матери',
        ];
        $tokens = array_values(array_filter(explode(' ', trim($value))));
        $meaningful = array_values(array_filter(
            $tokens,
            fn (string $token): bool => ! in_array($token, $stopWords, true)
        ));

        return implode(' ', $meaningful !== [] ? $meaningful : $tokens);
    }

    private function nameSimilarity(string $first, string $second): float
    {
        if ($first === '' || $second === '') {
            return 0.0;
        }

        similar_text($first, $second, $characters);
        $firstTokens = array_values(array_unique(array_filter(explode(' ', $first))));
        $secondTokens = array_values(array_unique(array_filter(explode(' ', $second))));
        $union = array_values(array_unique(array_merge($firstTokens, $secondTokens)));
        $intersection = array_intersect($firstTokens, $secondTokens);
        $tokens = $union === [] ? 0.0 : count($intersection) / count($union) * 100;

        return max((float) $characters, $tokens);
    }

    private function spatialCell(float $latitude, float $longitude): string
    {
        [$latCell, $lngCell] = $this->spatialCellParts($latitude, $longitude);

        return $latCell.':'.$lngCell;
    }

    private function spatialCellParts(float $latitude, float $longitude): array
    {
        return [(int) floor($latitude * 1000), (int) floor($longitude * 1000)];
    }

    private function distanceMeters(
        float $latitudeA,
        float $longitudeA,
        float $latitudeB,
        float $longitudeB
    ): int {
        $lat1 = deg2rad($latitudeA);
        $lat2 = deg2rad($latitudeB);
        $deltaLat = $lat2 - $lat1;
        $deltaLon = deg2rad($longitudeB - $longitudeA);
        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;

        return (int) round(6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';

        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }

    private function normalizeWebsite(?string $value): ?string
    {
        $value = trim(mb_strtolower((string) $value, 'UTF-8'));
        if ($value === '') {
            return null;
        }
        if (! preg_match('~^https?://~i', $value)) {
            $value = 'https://'.$value;
        }
        $host = preg_replace('/^www\./', '', (string) parse_url($value, PHP_URL_HOST));
        $path = rtrim((string) parse_url($value, PHP_URL_PATH), '/');

        return $host !== '' ? $host.$path : null;
    }

    private function normalizeAddress(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);

        return preg_replace('/[^a-zа-я0-9]+/ui', ' ', $value) ?: '';
    }

    private function resolveMappedSlug(string $slug, array $slugMap): string
    {
        $visited = [];
        while (isset($slugMap[$slug]) && $slugMap[$slug] !== $slug) {
            if (isset($visited[$slug])) {
                break;
            }
            $visited[$slug] = true;
            $slug = $slugMap[$slug];
        }

        return $slug;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
