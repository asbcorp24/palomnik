<?php

namespace App\Services;

use App\Models\PilgrimageObject;

class PilgrimageObjectSearchService
{
    private const EN_TO_RU_KEYBOARD = [
        'q' => 'й', 'w' => 'ц', 'e' => 'у', 'r' => 'к', 't' => 'е', 'y' => 'н',
        'u' => 'г', 'i' => 'ш', 'o' => 'щ', 'p' => 'з', '[' => 'х', ']' => 'ъ',
        'a' => 'ф', 's' => 'ы', 'd' => 'в', 'f' => 'а', 'g' => 'п', 'h' => 'р',
        'j' => 'о', 'k' => 'л', 'l' => 'д', ';' => 'ж', "'" => 'э',
        'z' => 'я', 'x' => 'ч', 'c' => 'с', 'v' => 'м', 'b' => 'и', 'n' => 'т',
        'm' => 'ь', ',' => 'б', '.' => 'ю', '`' => 'ё',
    ];

    private const LATIN_LOOKALIKES = [
        'a' => 'а', 'b' => 'в', 'c' => 'с', 'e' => 'е', 'h' => 'н', 'k' => 'к',
        'm' => 'м', 'o' => 'о', 'p' => 'р', 't' => 'т', 'x' => 'х', 'y' => 'у',
    ];

    /** @return array<int> */
    public function matchingIds(string $term): array
    {
        $variants = $this->queryVariants($term);

        if ($variants === []) {
            return [];
        }

        return PilgrimageObject::query()
            ->select([
                'id',
                'object_type_id',
                'name',
                'address',
                'short_description',
                'description',
                'history',
                'schedule_text',
            ])
            ->with([
                'objectType:id,name,slug',
                'sanctities:id,name',
                'publishedChildObjects.objectType:id,name,slug',
                'publishedChildObjects.sanctities:id,name',
            ])
            ->get()
            ->mapWithKeys(function (PilgrimageObject $object) use ($variants) {
                $score = $this->scoreObject($object, $variants);

                return $score === null ? [] : [$object->id => $score];
            })
            ->sortDesc()
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** @return array<string> */
    private function queryVariants(string $term): array
    {
        $lower = mb_strtolower(trim($term), 'UTF-8');
        $ruToEn = array_flip(self::EN_TO_RU_KEYBOARD);

        return collect([
            $term,
            strtr($lower, self::EN_TO_RU_KEYBOARD),
            strtr($lower, $ruToEn),
            strtr($lower, self::LATIN_LOOKALIKES),
        ])
            ->map(fn (string $variant) => $this->normalize($variant))
            ->filter(fn (string $variant) => $variant !== '')
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<string> $variants */
    private function scoreObject(PilgrimageObject $object, array $variants): ?int
    {
        $name = $this->normalize((string) $object->name);
        $type = $this->normalize(implode(' ', array_filter([
            $object->objectType?->name,
            $object->objectType?->slug,
        ])));
        $sanctities = $this->normalize($object->sanctities->pluck('name')->implode(' '));
        $children = $this->normalize($object->publishedChildObjects->map(function (PilgrimageObject $child) {
            return implode(' ', array_filter([
                $child->name,
                $child->objectType?->name,
                $child->objectType?->slug,
                $child->address,
                $child->short_description,
                $child->description,
                $child->history,
                $child->schedule_text,
                $child->sanctities->pluck('name')->implode(' '),
            ]));
        })->implode(' '));
        $allText = $this->normalize(implode(' ', array_filter([
            $object->name,
            $object->objectType?->name,
            $object->objectType?->slug,
            $object->address,
            $object->short_description,
            $object->description,
            $object->history,
            $object->schedule_text,
            $object->sanctities->pluck('name')->implode(' '),
            $children,
        ])));

        $bestScore = null;

        foreach ($variants as $variant) {
            $score = $this->tokenMatchScore($variant, $allText);

            if ($score === null) {
                continue;
            }

            if ($variant !== '' && str_contains($name, $variant)) {
                $score += 400;
            } elseif ($this->tokenMatchScore($variant, $name) !== null) {
                $score += 180;
            }

            if ($variant !== '' && str_contains($type, $variant)) {
                $score += 300;
            }

            if ($variant !== '' && str_contains($sanctities, $variant)) {
                $score += 250;
            }

            if ($this->tokenMatchScore($variant, $children) !== null) {
                $score += 120;
            }

            $bestScore = max($bestScore ?? 0, $score);
        }

        return $bestScore;
    }

    private function tokenMatchScore(string $query, string $text): ?int
    {
        if ($query === '' || $text === '') {
            return null;
        }

        if (str_contains($text, $query)) {
            return 1000;
        }

        $queryWords = $this->words($query);
        $textWords = $this->words($text);

        if ($queryWords === [] || $textWords === []) {
            return null;
        }

        $total = 0;

        foreach ($queryWords as $queryWord) {
            $bestWordScore = 0;
            $queryLength = mb_strlen($queryWord, 'UTF-8');

            foreach ($textWords as $textWord) {
                if ($queryWord === $textWord) {
                    $bestWordScore = 100;
                    break;
                }

                $textLength = mb_strlen($textWord, 'UTF-8');

                if ($queryLength >= 2 && str_starts_with($textWord, $queryWord)) {
                    $bestWordScore = max($bestWordScore, 90 - min(15, $textLength - $queryLength));
                    continue;
                }

                if ($queryLength >= 3 && str_contains($textWord, $queryWord)) {
                    $bestWordScore = max($bestWordScore, 78);
                    continue;
                }

                $maxLength = max($queryLength, $textLength);
                $allowedDistance = $maxLength >= 8 ? 2 : ($maxLength >= 3 ? 1 : 0);

                if ($allowedDistance === 0 || abs($queryLength - $textLength) > $allowedDistance) {
                    continue;
                }

                $distance = $this->unicodeLevenshtein($queryWord, $textWord);

                if ($distance <= $allowedDistance && ($distance / $maxLength) <= 0.28) {
                    $bestWordScore = max($bestWordScore, 72 - ($distance * 12));
                }
            }

            if ($bestWordScore === 0) {
                return null;
            }

            $total += $bestWordScore;
        }

        return $total;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /** @return array<string> */
    private function words(string $value): array
    {
        return preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function unicodeLevenshtein(string $left, string $right): int
    {
        $leftChars = preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $rightChars = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $previous = range(0, count($rightChars));

        foreach ($leftChars as $leftIndex => $leftChar) {
            $current = [$leftIndex + 1];

            foreach ($rightChars as $rightIndex => $rightChar) {
                $insert = $current[$rightIndex] + 1;
                $delete = $previous[$rightIndex + 1] + 1;
                $replace = $previous[$rightIndex] + ($leftChar === $rightChar ? 0 : 1);
                $current[] = min($insert, $delete, $replace);
            }

            $previous = $current;
        }

        return $previous[count($rightChars)];
    }
}
