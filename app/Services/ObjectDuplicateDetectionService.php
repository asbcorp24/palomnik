<?php

namespace App\Services;

use App\Models\ObjectDuplicateCandidate;
use App\Models\PilgrimageObject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ObjectDuplicateDetectionService
{
    public function scan(): array
    {
        $objects = PilgrimageObject::query()
            ->whereHas('objectType', fn ($query) => $query->visible())
            ->get([
                'id',
                'name',
                'address',
                'phone',
                'website',
                'latitude',
                'longitude',
                'parent_object_id',
                'object_type_id',
            ]);

        $pairs = $this->candidatePairs($objects);
        $candidates = [];

        foreach ($pairs as [$firstId, $secondId]) {
            $first = $objects->firstWhere('id', $firstId);
            $second = $objects->firstWhere('id', $secondId);

            if (! $first || ! $second) {
                continue;
            }

            $candidate = $this->evaluate($first, $second);
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        DB::transaction(function () use ($candidates): void {
            ObjectDuplicateCandidate::query()
                ->where('status', ObjectDuplicateCandidate::STATUS_PENDING)
                ->delete();

            foreach ($candidates as $candidate) {
                $existing = ObjectDuplicateCandidate::query()
                    ->where('object_a_id', $candidate['object_a_id'])
                    ->where('object_b_id', $candidate['object_b_id'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'score' => $candidate['score'],
                        'name_similarity' => $candidate['name_similarity'],
                        'distance_meters' => $candidate['distance_meters'],
                        'reasons' => $candidate['reasons'],
                    ]);
                    continue;
                }

                ObjectDuplicateCandidate::query()->create($candidate + [
                    'status' => ObjectDuplicateCandidate::STATUS_PENDING,
                ]);
            }
        });

        return [
            'objects' => $objects->count(),
            'pairs_checked' => count($pairs),
            'candidates' => count($candidates),
            'pending' => ObjectDuplicateCandidate::query()
                ->where('status', ObjectDuplicateCandidate::STATUS_PENDING)
                ->count(),
        ];
    }

    private function candidatePairs(Collection $objects): array
    {
        $pairs = [];
        $phoneBuckets = [];
        $websiteBuckets = [];
        $nameBuckets = [];
        $tokenBuckets = [];
        $geoBuckets = [];

        foreach ($objects as $object) {
            $phone = $this->normalizePhone($object->phone);
            if ($phone !== null) {
                $this->addFromBucket($pairs, $phoneBuckets, $phone, (int) $object->id);
            }

            $website = $this->normalizeWebsite($object->website);
            if ($website !== null) {
                $this->addFromBucket($pairs, $websiteBuckets, $website, (int) $object->id);
            }

            $name = $this->normalizeName($object->name);
            if ($name !== '') {
                $prefix = mb_substr($name, 0, 12, 'UTF-8');
                $this->addFromBucket($pairs, $nameBuckets, $prefix, (int) $object->id);

                $tokens = array_values(array_filter(explode(' ', $name)));
                sort($tokens);
                $signature = implode('|', array_slice($tokens, 0, 3));
                if ($signature !== '') {
                    $this->addFromBucket($pairs, $tokenBuckets, $signature, (int) $object->id);
                }
            }

            if ($this->hasCoordinates($object)) {
                $latCell = (int) floor(((float) $object->latitude) * 1000);
                $lngCell = (int) floor(((float) $object->longitude) * 1000);

                for ($latOffset = -1; $latOffset <= 1; $latOffset++) {
                    for ($lngOffset = -1; $lngOffset <= 1; $lngOffset++) {
                        $neighborKey = ($latCell + $latOffset).':'.($lngCell + $lngOffset);
                        foreach ($geoBuckets[$neighborKey] ?? [] as $existingId) {
                            $this->addPair($pairs, $existingId, (int) $object->id);
                        }
                    }
                }

                $geoBuckets[$latCell.':'.$lngCell][] = (int) $object->id;
            }
        }

        return array_values($pairs);
    }

    private function addFromBucket(array &$pairs, array &$buckets, string $key, int $id): void
    {
        foreach ($buckets[$key] ?? [] as $existingId) {
            $this->addPair($pairs, $existingId, $id);
        }

        $buckets[$key][] = $id;
    }

    private function addPair(array &$pairs, int $firstId, int $secondId): void
    {
        if ($firstId === $secondId) {
            return;
        }

        $a = min($firstId, $secondId);
        $b = max($firstId, $secondId);
        $pairs[$a.':'.$b] = [$a, $b];
    }

    private function evaluate(PilgrimageObject $first, PilgrimageObject $second): ?array
    {
        $nameA = $this->normalizeName($first->name);
        $nameB = $this->normalizeName($second->name);
        $similarity = $this->nameSimilarity($nameA, $nameB);
        $phoneSame = $this->normalizePhone($first->phone) !== null
            && $this->normalizePhone($first->phone) === $this->normalizePhone($second->phone);
        $websiteSame = $this->normalizeWebsite($first->website) !== null
            && $this->normalizeWebsite($first->website) === $this->normalizeWebsite($second->website);
        $distanceMeters = $this->distanceMeters($first, $second);

        $reasons = [];
        $score = 0.0;

        if ($nameA !== '' && $nameA === $nameB) {
            $reasons[] = 'Совпадает нормализованное название';
            $score += 38;
        } elseif ($similarity >= 85) {
            $reasons[] = 'Похожесть названий '.number_format($similarity, 0, ',', ' ').'%';
            $score += min(32, 20 + (($similarity - 85) * 0.8));
        }

        if ($distanceMeters !== null && $distanceMeters <= 100) {
            $reasons[] = 'Расстояние между координатами '.$distanceMeters.' м';
            $score += $distanceMeters <= 20 ? 35 : ($distanceMeters <= 50 ? 30 : 25);
        }

        if ($phoneSame) {
            $reasons[] = 'Совпадает телефон';
            $score += 32;
        }

        if ($websiteSame) {
            $reasons[] = 'Совпадает сайт';
            $score += 32;
        }

        if ($reasons === []) {
            return null;
        }

        if ($first->parent_object_id === $second->id || $second->parent_object_id === $first->id) {
            $reasons[] = 'Объекты уже связаны как родитель и дочерний объект';
            $score = max(0, $score - 20);
        }

        return [
            'object_a_id' => min((int) $first->id, (int) $second->id),
            'object_b_id' => max((int) $first->id, (int) $second->id),
            'score' => min(100, round($score, 2)),
            'name_similarity' => round($similarity, 2),
            'distance_meters' => $distanceMeters,
            'reasons' => $reasons,
        ];
    }

    private function normalizeName(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        $value = Str::ascii($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: '';

        $stopWords = [
            'hram', 'cerkov', 'cerkvi', 'sobor', 'chasovnya', 'monastyr',
            'prihod', 'svyatogo', 'svyatoy', 'svyataya', 'svyatyh', 'vo', 'v',
            'chest', 'ikony', 'bozhiey', 'materi', 'prepodobnogo', 'blazhennogo',
            'apostola', 'proroka', 'velikomuchenika', 'muchenika', 'moskovskiy',
        ];

        $tokens = array_values(array_filter(explode(' ', trim($value))));
        $meaningful = array_values(array_filter(
            $tokens,
            fn (string $token): bool => ! in_array($token, $stopWords, true)
        ));

        return trim(implode(' ', $meaningful !== [] ? $meaningful : $tokens));
    }

    private function nameSimilarity(string $first, string $second): float
    {
        if ($first === '' || $second === '') {
            return 0.0;
        }

        similar_text($first, $second, $characterSimilarity);

        $firstTokens = array_values(array_unique(array_filter(explode(' ', $first))));
        $secondTokens = array_values(array_unique(array_filter(explode(' ', $second))));
        $union = array_values(array_unique(array_merge($firstTokens, $secondTokens)));
        $intersection = array_intersect($firstTokens, $secondTokens);
        $tokenSimilarity = $union === [] ? 0.0 : (count($intersection) / count($union)) * 100;

        return max((float) $characterSimilarity, $tokenSimilarity);
    }

    private function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';
        if (strlen($digits) < 10) {
            return null;
        }

        return substr($digits, -10);
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

    private function hasCoordinates(PilgrimageObject $object): bool
    {
        return is_numeric($object->latitude)
            && is_numeric($object->longitude)
            && (float) $object->latitude !== 0.0
            && (float) $object->longitude !== 0.0;
    }

    private function distanceMeters(PilgrimageObject $first, PilgrimageObject $second): ?int
    {
        if (! $this->hasCoordinates($first) || ! $this->hasCoordinates($second)) {
            return null;
        }

        $lat1 = (float) $first->latitude;
        $lon1 = (float) $first->longitude;
        $lat2 = (float) $second->latitude;
        $lon2 = (float) $second->longitude;
        $latitudeDelta = deg2rad($lat2 - $lat1);
        $longitudeDelta = deg2rad($lon2 - $lon1);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($longitudeDelta / 2) ** 2;

        return (int) round(6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
