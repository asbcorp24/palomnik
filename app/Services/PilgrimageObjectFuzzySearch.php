<?php

namespace App\Services;

use App\Models\PilgrimageObject;
use Illuminate\Support\Collection;

class PilgrimageObjectFuzzySearch
{
    /** Rank objects by similarity to the supplied search phrase. */
    public function rank(Collection $objects, string $term): Collection
    {
        $queryVariants = $this->queryVariants($term);

        if ($queryVariants === []) {
            return $objects->values();
        }

        return $objects
            ->map(function (PilgrimageObject $object) use ($queryVariants) {
                $score = max(
                    $this->fieldScore($object->name, $queryVariants, 120),
                    $this->fieldScore($object->objectType?->name, $queryVariants, 115),
                    $this->fieldScore($object->objectType?->slug, $queryVariants, 105),
                    $this->fieldScore($object->address, $queryVariants, 85),
                    $this->fieldScore($object->short_description, $queryVariants, 65),
                    $this->fieldScore($object->description, $queryVariants, 45),
                    $object->sanctities
                        ->map(fn ($sanctity) => $this->fieldScore($sanctity->name, $queryVariants, 105))
                        ->max() ?? 0,
                    $object->publishedChildObjects
                        ->map(function (PilgrimageObject $child) use ($queryVariants) {
                            return max(
                                $this->fieldScore($child->name, $queryVariants, 90),
                                $this->fieldScore($child->objectType?->name, $queryVariants, 100),
                                $this->fieldScore($child->objectType?->slug, $queryVariants, 90),
                                $this->fieldScore($child->address, $queryVariants, 60),
                                $this->fieldScore($child->short_description, $queryVariants, 55),
                                $child->sanctities
                                    ->map(fn ($sanctity) => $this->fieldScore($sanctity->name, $queryVariants, 75))
                                    ->max() ?? 0,
                            );
                        })
                        ->max() ?? 0,
                );

                return [
                    'object' => $object,
                    'score' => $score,
                ];
            })
            ->filter(fn (array $item) => $item['score'] > 0)
            ->sort(function (array $left, array $right) {
                if ($left['score'] === $right['score']) {
                    return strnatcasecmp($left['object']->name, $right['object']->name);
                }

                return $right['score'] <=> $left['score'];
            })
            ->pluck('object')
            ->values();
    }

    private function fieldScore(?string $value, array $queryVariants, int $weight): int
    {
        $fieldVariants = $this->fieldVariants((string) $value);
        $best = 0;

        foreach ($queryVariants as $query) {
            foreach ($fieldVariants as $field) {
                if ($field === '' || $query === '') {
                    continue;
                }

                if ($field === $query) {
                    $best = max($best, $weight + 50);
                    continue;
                }

                if (str_contains($field, $query)) {
                    $best = max($best, $weight + 35);
                    continue;
                }

                $tokenScore = $this->tokenScore($field, $query);
                if ($tokenScore > 0) {
                    $best = max($best, $weight + $tokenScore);
                }
            }
        }

        return $best;
    }

    private function tokenScore(string $field, string $query): int
    {
        $fieldWords = array_values(array_filter(explode(' ', $field)));
        $queryWords = array_values(array_filter(explode(' ', $query)));

        if ($fieldWords === [] || $queryWords === []) {
            return 0;
        }

        $scores = [];

        foreach ($queryWords as $queryWord) {
            $bestWordScore = 0;

            foreach ($fieldWords as $fieldWord) {
                if ($fieldWord === $queryWord) {
                    $bestWordScore = max($bestWordScore, 30);
                    continue;
                }

                if (str_starts_with($fieldWord, $queryWord) || str_starts_with($queryWord, $fieldWord)) {
                    $bestWordScore = max($bestWordScore, 25);
                    continue;
                }

                if (strlen($queryWord) >= 3 && str_contains($fieldWord, $queryWord)) {
                    $bestWordScore = max($bestWordScore, 22);
                    continue;
                }

                $distance = levenshtein($fieldWord, $queryWord);
                $allowedDistance = $this->allowedDistance(max(strlen($fieldWord), strlen($queryWord)));

                if ($distance <= $allowedDistance) {
                    $bestWordScore = max($bestWordScore, max(8, 20 - ($distance * 5)));
                }
            }

            if ($bestWordScore === 0) {
                return 0;
            }

            $scores[] = $bestWordScore;
        }

        return (int) round(array_sum($scores) / count($scores));
    }

    private function allowedDistance(int $length): int
    {
        return match (true) {
            $length <= 2 => 0,
            $length <= 5 => 1,
            $length <= 9 => 2,
            default => 3,
        };
    }

    private function queryVariants(string $value): array
    {
        $normalized = $this->normalize($value);
        $variants = $this->fieldVariants($normalized);

        $keyboardCorrected = $this->normalize($this->englishKeyboardToRussian($value));
        $visualCorrected = $this->normalize($this->visualLatinToCyrillic($value));

        return collect([
            ...$variants,
            ...$this->fieldVariants($keyboardCorrected),
            ...$this->fieldVariants($visualCorrected),
        ])->filter()->unique()->values()->all();
    }

    private function fieldVariants(string $value): array
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return [];
        }

        return collect([
            $normalized,
            $this->transliterate($normalized),
        ])->filter()->unique()->values()->all();
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function transliterate(string $value): string
    {
        return strtr($value, [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'i',
            'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
            'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh',
            'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e',
            'ю' => 'yu', 'я' => 'ya',
        ]);
    }

    private function englishKeyboardToRussian(string $value): string
    {
        return strtr(mb_strtolower($value, 'UTF-8'), [
            'q' => 'й', 'w' => 'ц', 'e' => 'у', 'r' => 'к', 't' => 'е',
            'y' => 'н', 'u' => 'г', 'i' => 'ш', 'o' => 'щ', 'p' => 'з',
            '[' => 'х', ']' => 'ъ', 'a' => 'ф', 's' => 'ы', 'd' => 'в',
            'f' => 'а', 'g' => 'п', 'h' => 'р', 'j' => 'о', 'k' => 'л',
            'l' => 'д', ';' => 'ж', "'" => 'э', 'z' => 'я', 'x' => 'ч',
            'c' => 'с', 'v' => 'м', 'b' => 'и', 'n' => 'т', 'm' => 'ь',
            ',' => 'б', '.' => 'ю', '`' => 'е',
        ]);
    }

    private function visualLatinToCyrillic(string $value): string
    {
        return strtr(mb_strtolower($value, 'UTF-8'), [
            'a' => 'а', 'b' => 'в', 'c' => 'с', 'e' => 'е', 'h' => 'н',
            'k' => 'к', 'm' => 'м', 'o' => 'о', 'p' => 'р', 't' => 'т',
            'x' => 'х', 'y' => 'у',
        ]);
    }
}
