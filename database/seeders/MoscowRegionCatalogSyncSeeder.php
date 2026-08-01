<?php

namespace Database\Seeders;

use App\Services\MonasteryTempleLinkService;
use App\Services\MoscowRegionCatalogSyncService;
use Illuminate\Database\Seeder;

class MoscowRegionCatalogSyncSeeder extends Seeder
{
    public function run(): void
    {
        $objectsPath = getenv('MOSCOW_REGION_OBJECTS_JSON')
            ?: database_path('seeders/data/moscow-region-orthodox-places.json');
        $nearbyPath = getenv('MOSCOW_REGION_NEARBY_JSON')
            ?: database_path('seeders/data/moscow-region-nearby-points.json');
        $cleanValue = getenv('MOSCOW_REGION_SYNC_CLEAN');
        $clean = $cleanValue === false
            ? true
            : filter_var($cleanValue, FILTER_VALIDATE_BOOLEAN);

        $result = app(MoscowRegionCatalogSyncService::class)->sync(
            (string) $objectsPath,
            (string) $nearbyPath,
            $clean
        );
        $hierarchy = $clean
            ? app(MonasteryTempleLinkService::class)->link(true, true, true, 600)
            : null;

        $objects = $result['objects'];
        $points = $result['points'];
        $prepared = $result['prepared'];

        $this->command?->info('Синхронизация каталога завершена.');
        $this->command?->line('Пакет: '.$result['batch_id']);
        $this->command?->line(
            'Объекты: создано '.$objects['created']
            .', обновлено '.$objects['updated']
            .', без изменений '.$objects['unchanged']
            .', объединено дублей '.$objects['duplicates_merged']
            .', объединено приделов '.$objects['auxiliary_merged']
            .', архивировано приделов '.$objects['auxiliary_archived']
            .', архивировано устаревших '.$objects['stale_archived'].'.'
        );
        $this->command?->line(
            'Точки рядом: создано '.$points['created']
            .', обновлено '.$points['updated']
            .', без изменений '.$points['unchanged']
            .', архивировано устаревших '.$points['stale_archived'].'.'
        );
        $this->command?->line(
            'Подготовка JSON: готово '.$prepared['ready']
            .', удалено приделов '.$prepared['auxiliary_removed']
            .', объединено дублей '.$prepared['nearby_duplicates_merged'].'.'
        );

        if ($hierarchy !== null) {
            $this->command?->line(
                'Иерархия монастырей: проверено '.$hierarchy['scanned']
                .', привязано храмов и часовен '.$hierarchy['linked']
                .', неоднозначных '.$hierarchy['ambiguous'].'.'
            );
        } else {
            $this->command?->comment(
                'Иерархия монастырей не изменялась в режиме без очистки.'
            );
        }
    }
}
