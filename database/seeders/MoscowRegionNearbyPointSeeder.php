<?php

namespace Database\Seeders;

use App\Models\PilgrimageObject;
use App\Models\PointOfInterest;
use Illuminate\Database\Seeder;
use JsonException;
use RuntimeException;

class MoscowRegionNearbyPointSeeder extends Seeder
{
    /** @throws JsonException */
    public function run(): void
    {
        $path = database_path('seeders/data/moscow-region-nearby-points.json');

        if (! is_file($path)) {
            throw new RuntimeException(
                'Не найден JSON ближайших точек. Сначала выполните: '
                .'python scripts/fetch_moscow_region_churches_from_pbf.py '
                .'storage/app/moscow-region.osm.pbf'
            );
        }

        $snapshot = json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $points = $snapshot['points'] ?? null;
        if (! is_array($points)) {
            throw new RuntimeException(
                'Некорректный формат JSON: отсутствует массив points.'
            );
        }

        $allowedCategories = ['parking', 'cafe', 'hotel'];
        $objectCache = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($points as $item) {
            if (! is_array($item)) {
                $skipped++;
                continue;
            }

            $objectSlug = trim((string) ($item['object_slug'] ?? ''));
            $category = trim((string) ($item['category'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));
            $latitude = (float) ($item['latitude'] ?? 0);
            $longitude = (float) ($item['longitude'] ?? 0);

            if (
                $objectSlug === ''
                || $name === ''
                || ! in_array($category, $allowedCategories, true)
                || $latitude === 0.0
                || $longitude === 0.0
            ) {
                $skipped++;
                continue;
            }

            if (! array_key_exists($objectSlug, $objectCache)) {
                $objectCache[$objectSlug] = PilgrimageObject::query()
                    ->where('slug', $objectSlug)
                    ->first();
            }

            $object = $objectCache[$objectSlug];
            if (! $object) {
                $skipped++;
                continue;
            }

            $point = PointOfInterest::withTrashed()
                ->where('pilgrimage_object_id', $object->id)
                ->where('category', $category)
                ->where('latitude', $latitude)
                ->where('longitude', $longitude)
                ->first();

            // Удалённые администратором точки автоматически не восстанавливаем.
            if ($point?->trashed()) {
                $skipped++;
                continue;
            }

            $incoming = [
                'pilgrimage_object_id' => $object->id,
                'category' => $category,
                'name' => $name,
                'description' => $this->nullableString(
                    $item['description'] ?? null
                ),
                'address' => $this->nullableString($item['address'] ?? null),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'phone' => $this->nullableString($item['phone'] ?? null),
                'website' => $this->nullableString($item['website'] ?? null),
                'schedule_text' => $this->nullableString(
                    $item['schedule_text'] ?? null
                ),
                'is_published' => true,
                'sort_order' => max(0, (int) ($item['sort_order'] ?? 0)),
            ];

            if (! $point) {
                PointOfInterest::query()->create($incoming);
                $created++;
                continue;
            }

            $point->update($incoming);
            $updated++;
        }

        $this->command?->info(
            "Импорт ближайших точек завершён: создано {$created}, "
            ."обновлено {$updated}, пропущено {$skipped}."
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
