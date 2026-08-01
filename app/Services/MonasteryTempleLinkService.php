<?php

namespace App\Services;

use App\Models\PilgrimageObject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MonasteryTempleLinkService
{
    private const DEFAULT_RADIUS_METERS = 600;
    private const MIN_RADIUS_METERS = 100;
    private const MAX_RADIUS_METERS = 1500;
    private const RESULT_SAMPLE_LIMIT = 200;

    private AdminActivityLogger $activityLogger;

    public function __construct(AdminActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    public function link(
        bool $apply = false,
        bool $osmOnly = true,
        bool $includeChapels = true,
        int $radiusMeters = self::DEFAULT_RADIUS_METERS
    ): array {
        $radiusMeters = max(
            self::MIN_RADIUS_METERS,
            min(self::MAX_RADIUS_METERS, $radiusMeters)
        );
        $childTypes = $includeChapels ? ['temple', 'chapel'] : ['temple'];
        $monasteries = $this->monasteryQuery($osmOnly)->get();
        $stats = [
            'apply' => $apply,
            'osm_only' => $osmOnly,
            'include_chapels' => $includeChapels,
            'radius_meters' => $radiusMeters,
            'monasteries' => $monasteries->count(),
            'scanned' => 0,
            'would_link' => 0,
            'linked' => 0,
            'ambiguous' => 0,
            'no_candidate' => 0,
            'samples' => [],
        ];

        $query = PilgrimageObject::query()
            ->published()
            ->whereNull('parent_object_id')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereHas('objectType', function ($query) use ($childTypes): void {
                $query->visible()->whereIn('slug', $childTypes);
            });

        if ($osmOnly) {
            $query->where('slug', 'like', 'osm-%');
        }

        $query->orderBy('id')->chunkById(250, function ($children) use (
            $monasteries,
            $radiusMeters,
            $apply,
            &$stats
        ): void {
            foreach ($children as $child) {
                $stats['scanned']++;
                $resolution = $this->resolveParent($child, $monasteries, $radiusMeters);

                if ($resolution['status'] === 'none') {
                    $stats['no_candidate']++;
                    continue;
                }

                if ($resolution['status'] === 'ambiguous') {
                    $stats['ambiguous']++;
                    $this->appendSample($stats, $child, $resolution);
                    continue;
                }

                $stats['would_link']++;
                $this->appendSample($stats, $child, $resolution);

                if (! $apply) {
                    continue;
                }

                $parent = $resolution['parent'];
                DB::transaction(function () use ($child, $parent): void {
                    $locked = PilgrimageObject::query()
                        ->whereKey($child->id)
                        ->whereNull('parent_object_id')
                        ->lockForUpdate()
                        ->first();

                    if (! $locked) {
                        return;
                    }

                    $locked->update(['parent_object_id' => $parent->id]);
                });

                $freshParentId = PilgrimageObject::query()
                    ->whereKey($child->id)
                    ->value('parent_object_id');

                if ((int) $freshParentId === (int) $parent->id) {
                    $stats['linked']++;
                    Cache::forget('map:object-detail:'.$child->id);
                    Cache::forget('map:object-detail:'.$parent->id);
                }
            }
        });

        if ($apply && $stats['linked'] > 0) {
            $batchId = 'monastery-links-'.now()->format('YmdHis');
            $this->activityLogger->log(
                'bulk_link_monastery_children',
                null,
                null,
                null,
                [
                    'linked' => $stats['linked'],
                    'scanned' => $stats['scanned'],
                    'ambiguous' => $stats['ambiguous'],
                    'radius_meters' => $stats['radius_meters'],
                    'osm_only' => $stats['osm_only'],
                    'include_chapels' => $stats['include_chapels'],
                    'samples' => array_slice($stats['samples'], 0, 50),
                ],
                null,
                app()->runningInConsole() ? 'console' : 'web',
                $batchId,
                PilgrimageObject::class,
                null,
                'Автоматическая привязка храмов к монастырям'
            );
        }

        return $stats;
    }

    private function monasteryQuery(bool $osmOnly)
    {
        $query = PilgrimageObject::query()
            ->published()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereHas('objectType', function ($query): void {
                $query->visible()->where('slug', 'monastery');
            })
            ->orderBy('id')
            ->select([
                'id',
                'vicariate_id',
                'deanery_id',
                'name',
                'slug',
                'address',
                'latitude',
                'longitude',
                'phone',
                'website',
            ]);

        if ($osmOnly) {
            $query->where('slug', 'like', 'osm-%');
        }

        return $query;
    }

    private function resolveParent(
        PilgrimageObject $child,
        Collection $monasteries,
        int $radiusMeters
    ): array {
        $ranked = [];

        foreach ($monasteries as $monastery) {
            if ((int) $monastery->id === (int) $child->id) {
                continue;
            }

            $distance = $this->distanceMeters(
                (float) $child->latitude,
                (float) $child->longitude,
                (float) $monastery->latitude,
                (float) $monastery->longitude
            );

            if ($distance > $radiusMeters) {
                continue;
            }

            $evidence = $this->evidence($child, $monastery, $distance);
            $ranked[] = [
                'parent' => $monastery,
                'distance' => $distance,
                'score' => $evidence['score'],
                'signals' => $evidence['signals'],
                'strong' => $evidence['strong'],
            ];
        }

        if ($ranked === []) {
            return ['status' => 'none'];
        }

        usort($ranked, function (array $first, array $second): int {
            return $second['score'] <=> $first['score']
                ?: $first['distance'] <=> $second['distance'];
        });

        $best = $ranked[0];
        $second = $ranked[1] ?? null;
        $uniqueVeryClose = $best['distance'] <= 35
            && ($second === null || $second['distance'] >= 100);
        $qualified = ($best['strong'] && $best['score'] >= 80)
            || $uniqueVeryClose;

        if (! $qualified) {
            return ['status' => 'none'];
        }

        $scoreGap = $second === null ? PHP_INT_MAX : $best['score'] - $second['score'];
        $distanceGap = $second === null ? PHP_INT_MAX : $second['distance'] - $best['distance'];
        $ambiguous = $second !== null
            && $scoreGap < 18
            && $distanceGap < 120
            && ! $this->hasExclusiveStrongSignal($best, $second);

        if ($ambiguous) {
            return [
                'status' => 'ambiguous',
                'parent' => $best['parent'],
                'score' => $best['score'],
                'distance' => $best['distance'],
                'signals' => $best['signals'],
                'alternative' => [
                    'id' => $second['parent']->id,
                    'name' => $second['parent']->name,
                    'score' => $second['score'],
                    'distance' => $second['distance'],
                ],
            ];
        }

        return [
            'status' => 'match',
            'parent' => $best['parent'],
            'score' => $best['score'],
            'distance' => $best['distance'],
            'signals' => $best['signals'],
        ];
    }

    private function evidence(
        PilgrimageObject $child,
        PilgrimageObject $monastery,
        int $distance
    ): array {
        $signals = [];
        $score = $this->distanceScore($distance);
        $childAddress = $this->normalizeAddress($child->address);
        $parentAddress = $this->normalizeAddress($monastery->address);
        $sameAddress = $childAddress !== ''
            && $parentAddress !== ''
            && ! $this->isGenericAddress($childAddress)
            && $childAddress === $parentAddress;
        $sameWebsite = $this->normalizeWebsite($child->website) !== null
            && $this->normalizeWebsite($child->website) === $this->normalizeWebsite($monastery->website);
        $samePhone = $this->normalizePhone($child->phone) !== null
            && $this->normalizePhone($child->phone) === $this->normalizePhone($monastery->phone);
        $nameRelated = $this->namesIndicateMonasteryRelation($child->name, $monastery->name);

        if ($sameAddress) {
            $score += 60;
            $signals[] = 'same_address';
        }
        if ($sameWebsite) {
            $score += 55;
            $signals[] = 'same_website';
        }
        if ($samePhone) {
            $score += 45;
            $signals[] = 'same_phone';
        }
        if ($nameRelated) {
            $score += 50;
            $signals[] = 'name_relation';
        }
        if ($child->deanery_id !== null
            && (int) $child->deanery_id === (int) $monastery->deanery_id) {
            $score += 8;
            $signals[] = 'same_deanery';
        } elseif ($child->vicariate_id !== null
            && (int) $child->vicariate_id === (int) $monastery->vicariate_id) {
            $score += 4;
            $signals[] = 'same_vicariate';
        }

        return [
            'score' => $score,
            'signals' => $signals,
            'strong' => $sameAddress || $sameWebsite || $samePhone || $nameRelated,
        ];
    }

    private function hasExclusiveStrongSignal(array $best, array $second): bool
    {
        $exclusive = ['same_address', 'same_website', 'same_phone', 'name_relation'];
        $bestSignals = array_intersect($exclusive, $best['signals']);
        $secondSignals = array_intersect($exclusive, $second['signals']);

        return count($bestSignals) > count($secondSignals)
            && $best['score'] >= $second['score'] + 10;
    }

    private function appendSample(array &$stats, PilgrimageObject $child, array $resolution): void
    {
        if (count($stats['samples']) >= self::RESULT_SAMPLE_LIMIT) {
            return;
        }

        $stats['samples'][] = [
            'status' => $resolution['status'],
            'child_id' => $child->id,
            'child_name' => $child->name,
            'child_slug' => $child->slug,
            'parent_id' => $resolution['parent']->id,
            'parent_name' => $resolution['parent']->name,
            'parent_slug' => $resolution['parent']->slug,
            'distance_meters' => $resolution['distance'],
            'score' => $resolution['score'],
            'signals' => $resolution['signals'],
            'alternative' => $resolution['alternative'] ?? null,
        ];
    }

    private function distanceScore(int $distance): int
    {
        if ($distance <= 25) {
            return 70;
        }
        if ($distance <= 50) {
            return 60;
        }
        if ($distance <= 80) {
            return 50;
        }
        if ($distance <= 150) {
            return 35;
        }
        if ($distance <= 300) {
            return 20;
        }

        return 10;
    }

    private function namesIndicateMonasteryRelation(?string $childName, ?string $parentName): bool
    {
        $child = mb_strtolower((string) $childName, 'UTF-8');
        if (! str_contains($child, 'монастыр')) {
            return false;
        }

        $childTokens = $this->meaningfulNameTokens($childName);
        $parentTokens = $this->meaningfulNameTokens($parentName);
        if ($childTokens === [] || $parentTokens === []) {
            return false;
        }

        $shared = array_intersect($childTokens, $parentTokens);

        return count($shared) >= 1;
    }

    private function meaningfulNameTokens(?string $value): array
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/[^а-яa-z0-9]+/ui', ' ', $value) ?: '';
        $stopWords = [
            'храм', 'церковь', 'собор', 'часовня', 'монастырь', 'монастыря',
            'монастыре', 'монастырский', 'мужской', 'женский', 'православный',
            'православная', 'приход', 'подворье', 'во', 'в', 'при', 'на', 'у',
            'святого', 'святой', 'святая', 'святых', 'иконы', 'божией', 'матери',
        ];
        $tokens = [];

        foreach (array_filter(explode(' ', trim($value))) as $token) {
            if (in_array($token, $stopWords, true) || mb_strlen($token, 'UTF-8') < 4) {
                continue;
            }

            $stem = preg_replace(
                '/(ского|скому|ском|ская|ской|скую|ские|ских|скими|ский|ское|ого|ому|ему|ыми|ими|ый|ий|ой|ая|яя|ое|ее)$/u',
                '',
                $token
            ) ?: $token;
            $tokens[] = $stem;
        }

        return array_values(array_unique($tokens));
    }

    private function normalizeAddress(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);

        return trim(preg_replace('/[^а-яa-z0-9]+/ui', ' ', $value) ?: '');
    }

    private function isGenericAddress(string $value): bool
    {
        return $value === '' || in_array($value, [
            'адрес уточняется',
            'москва',
            'московская область',
            'москва и московская область',
        ], true);
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

        $host = parse_url($value, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', $host) ?: $host;
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
}
