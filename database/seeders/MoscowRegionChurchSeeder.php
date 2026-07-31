<?php

namespace Database\Seeders;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use Illuminate\Database\Seeder;
use JsonException;
use RuntimeException;

class MoscowRegionChurchSeeder extends Seeder
{
    /** @throws JsonException */
    public function run(): void
    {
        $path = database_path('seeders/data/moscow-region-orthodox-places.json');

        if (! is_file($path)) {
            throw new RuntimeException(
                'Не найден JSON со списком объектов. Сначала выполните: '
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

        $objects = $snapshot['objects'] ?? null;
        if (! is_array($objects)) {
            throw new RuntimeException(
                'Некорректный формат JSON: отсутствует массив objects.'
            );
        }

        $allowedTypes = ['temple', 'monastery', 'chapel', 'holy-spring'];

        $typeIds = ObjectType::query()
            ->whereIn('slug', $allowedTypes)
            ->pluck('id', 'slug');

        foreach ($allowedTypes as $slug) {
            if (! $typeIds->has($slug)) {
                throw new RuntimeException(
                    'Не найден тип объекта '.$slug
                    .'. Сначала выполните CatalogSeeder.'
                );
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($objects as $item) {
            if (! is_array($item)) {
                $skipped++;
                continue;
            }

            $slug = trim((string) ($item['slug'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));
            $type = trim((string) ($item['type'] ?? ''));

            if ($slug === '' || $name === '' || ! $typeIds->has($type)) {
                $skipped++;
                continue;
            }

            $object = PilgrimageObject::withTrashed()
                ->where('slug', $slug)
                ->first();

            // Удалённые администратором объекты не восстанавливаем автоматически.
            if ($object?->trashed()) {
                $skipped++;
                continue;
            }

            $incoming = [
                'object_type_id' => $typeIds[$type],
                'name' => $name,
                'address' => trim((string) ($item['address'] ?? ''))
                    ?: 'Адрес уточняется',
                'latitude' => (float) ($item['latitude'] ?? 0),
                'longitude' => (float) ($item['longitude'] ?? 0),
                'phone' => $this->nullableString($item['phone'] ?? null),
                'email' => $this->nullableString($item['email'] ?? null),
                'website' => $this->nullableString($item['website'] ?? null),
                'schedule_text' => $this->nullableString(
                    $item['schedule_text'] ?? null
                ),
            ];

            if (! $object) {
                PilgrimageObject::query()->create($incoming + [
                    'slug' => $slug,
                    'is_published' => true,
                    'published_at' => now(),
                ]);
                $created++;
                continue;
            }

            // Тип и координаты синхронизируем всегда. Заполненные редакторские
            // поля не затираем данными из OpenStreetMap.
            $updates = [
                'object_type_id' => $incoming['object_type_id'],
                'latitude' => $incoming['latitude'],
                'longitude' => $incoming['longitude'],
            ];

            foreach (
                ['name', 'address', 'phone', 'email', 'website', 'schedule_text']
                as $field
            ) {
                if (blank($object->{$field}) && filled($incoming[$field])) {
                    $updates[$field] = $incoming[$field];
                }
            }

            $object->update($updates);
            $updated++;
        }

        $this->command?->info(
            "Импорт объектов завершён: создано {$created}, "
            ."обновлено {$updated}, пропущено {$skipped}."
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
