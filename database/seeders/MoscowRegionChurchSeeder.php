<?php

namespace Database\Seeders;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Services\AdminActivityLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
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

        $allowedTypes = ['temple', 'monastery', 'chapel'];

        $typeIds = ObjectType::query()
            ->visible()
            ->whereIn('slug', $allowedTypes)
            ->pluck('id', 'slug');

        foreach ($allowedTypes as $slug) {
            if (! $typeIds->has($slug)) {
                throw new RuntimeException(
                    'Не найден активный публичный тип объекта '.$slug
                    .'. Сначала выполните CatalogSeeder.'
                );
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $removed = 0;
        $importedSlugs = [];

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

            $importedSlugs[$slug] = true;

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
                'short_description' => $this->nullableString(
                    $item['short_description'] ?? null
                ),
                'description' => $this->nullableString(
                    $item['description'] ?? null
                ),
                'history' => $this->nullableString($item['history'] ?? null),
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
            // поля не затираем данными из OpenStreetMap или Ollama.
            $updates = [
                'object_type_id' => $incoming['object_type_id'],
                'latitude' => $incoming['latitude'],
                'longitude' => $incoming['longitude'],
            ];

            foreach (
                [
                    'name',
                    'short_description',
                    'description',
                    'history',
                    'address',
                    'phone',
                    'email',
                    'website',
                    'schedule_text',
                ] as $field
            ) {
                if (blank($object->{$field}) && filled($incoming[$field])) {
                    $updates[$field] = $incoming[$field];
                }
            }

            $object->update($updates);
            $updated++;
        }

        // Очистку выполняем только после явной проверки Ollama. Обычный сырой
        // OSM-файл не может случайно удалить ранее импортированные объекты.
        if (isset($snapshot['meta']['ollama_review'])) {
            PilgrimageObject::query()
                ->where('slug', 'like', 'osm-%')
                ->select(['id', 'slug'])
                ->chunkById(500, function (Collection $rows) use (
                    $importedSlugs,
                    &$removed
                ): void {
                    foreach ($rows as $row) {
                        if (! isset($importedSlugs[$row->slug])) {
                            $row->delete();
                            $removed++;
                        }
                    }
                });
        }

        $batchId = 'church-import-'.now()->format('YmdHis');
        app(AdminActivityLogger::class)->log(
            'import',
            null,
            null,
            null,
            [
                'importer' => self::class,
                'file' => $path,
                'file_sha256' => hash_file('sha256', $path),
                'source_objects' => count($objects),
                'created' => $created,
                'updated' => $updated,
                'removed' => $removed,
                'skipped' => $skipped,
                'ollama_reviewed' => isset($snapshot['meta']['ollama_review']),
            ],
            null,
            'import',
            $batchId,
            PilgrimageObject::class,
            null,
            'Импорт храмов и монастырей Москвы и Подмосковья'
        );

        $this->command?->info(
            "Импорт объектов завершён: создано {$created}, "
            ."обновлено {$updated}, удалено после проверки {$removed}, "
            ."пропущено {$skipped}. Пакет: {$batchId}."
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
