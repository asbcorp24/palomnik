<?php

namespace App\Services;

use App\Models\PilgrimageObject;

class ObjectEditorialCompletenessService
{
    public const WEIGHTS = [
        'name' => 10,
        'address' => 10,
        'coordinates' => 10,
        'description' => 15,
        'history' => 10,
        'schedule' => 15,
        'contacts' => 10,
        'photos' => 10,
        'sanctities' => 5,
        'parking' => 2.5,
        'accessibility' => 2.5,
    ];

    public function score(PilgrimageObject $object): int
    {
        return (int) round(collect($this->breakdown($object))->sum(
            fn (array $criterion): float => $criterion['filled'] ? (float) $criterion['weight'] : 0.0
        ));
    }

    public function breakdown(PilgrimageObject $object): array
    {
        return [
            'name' => $this->criterion('Название', self::WEIGHTS['name'], filled($object->name)),
            'address' => $this->criterion('Адрес', self::WEIGHTS['address'], filled($object->address)),
            'coordinates' => $this->criterion(
                'Координаты',
                self::WEIGHTS['coordinates'],
                $object->latitude !== null && $object->longitude !== null
            ),
            'description' => $this->criterion(
                'Описание',
                self::WEIGHTS['description'],
                filled($object->short_description) || filled($object->description)
            ),
            'history' => $this->criterion('История', self::WEIGHTS['history'], filled($object->history)),
            'schedule' => $this->criterion('Расписание', self::WEIGHTS['schedule'], filled($object->schedule_text)),
            'contacts' => $this->criterion(
                'Контакты',
                self::WEIGHTS['contacts'],
                filled($object->phone) || filled($object->email) || filled($object->website)
            ),
            'photos' => $this->criterion('Фотографии', self::WEIGHTS['photos'], $this->hasPhotos($object)),
            'sanctities' => $this->criterion('Святыни', self::WEIGHTS['sanctities'], $this->hasSanctities($object)),
            'parking' => $this->criterion('Парковка', self::WEIGHTS['parking'], filled($object->parking_info)),
            'accessibility' => $this->criterion(
                'Доступность',
                self::WEIGHTS['accessibility'],
                filled($object->accessibility_info)
            ),
        ];
    }

    public function missingLabels(PilgrimageObject $object): array
    {
        return collect($this->breakdown($object))
            ->reject(fn (array $criterion): bool => $criterion['filled'])
            ->pluck('label')
            ->values()
            ->all();
    }

    public function sqlExpression(string $table = 'pilgrimage_objects'): string
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: 'pilgrimage_objects';

        return "(
            CASE WHEN TRIM(COALESCE({$table}.name, '')) <> '' THEN 10 ELSE 0 END
            + CASE WHEN TRIM(COALESCE({$table}.address, '')) <> '' THEN 10 ELSE 0 END
            + CASE WHEN {$table}.latitude IS NOT NULL AND {$table}.longitude IS NOT NULL THEN 10 ELSE 0 END
            + CASE WHEN TRIM(COALESCE({$table}.short_description, '')) <> '' OR TRIM(COALESCE({$table}.description, '')) <> '' THEN 15 ELSE 0 END
            + CASE WHEN TRIM(COALESCE({$table}.history, '')) <> '' THEN 10 ELSE 0 END
            + CASE WHEN TRIM(COALESCE({$table}.schedule_text, '')) <> '' THEN 15 ELSE 0 END
            + CASE WHEN TRIM(COALESCE({$table}.phone, '')) <> '' OR TRIM(COALESCE({$table}.email, '')) <> '' OR TRIM(COALESCE({$table}.website, '')) <> '' THEN 10 ELSE 0 END
            + CASE WHEN EXISTS (
                SELECT 1 FROM object_media completeness_media
                WHERE completeness_media.pilgrimage_object_id = {$table}.id
                  AND completeness_media.type = 'image'
            ) THEN 10 ELSE 0 END
            + CASE WHEN EXISTS (
                SELECT 1 FROM object_sanctity completeness_sanctity
                WHERE completeness_sanctity.pilgrimage_object_id = {$table}.id
            ) THEN 5 ELSE 0 END
            + CASE WHEN TRIM(COALESCE({$table}.parking_info, '')) <> '' THEN 2.5 ELSE 0 END
            + CASE WHEN TRIM(COALESCE({$table}.accessibility_info, '')) <> '' THEN 2.5 ELSE 0 END
        )";
    }

    private function criterion(string $label, float|int $weight, bool $filled): array
    {
        return [
            'label' => $label,
            'weight' => $weight,
            'filled' => $filled,
        ];
    }

    private function hasPhotos(PilgrimageObject $object): bool
    {
        if (array_key_exists('image_media_count', $object->getAttributes())) {
            return (int) $object->getAttribute('image_media_count') > 0;
        }

        if ($object->relationLoaded('media')) {
            return $object->media->contains(fn ($media): bool => $media->type === 'image');
        }

        return $object->media()->where('type', 'image')->exists();
    }

    private function hasSanctities(PilgrimageObject $object): bool
    {
        if (array_key_exists('sanctities_count', $object->getAttributes())) {
            return (int) $object->getAttribute('sanctities_count') > 0;
        }

        if ($object->relationLoaded('sanctities')) {
            return $object->sanctities->isNotEmpty();
        }

        return $object->sanctities()->exists();
    }
}
