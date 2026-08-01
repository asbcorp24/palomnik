<?php

namespace App\Console\Commands;

use App\Services\MoscowRegionCatalogSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncMoscowRegionCatalog extends Command
{
    protected $signature = 'catalog:sync-moscow
        {objects=database/seeders/data/moscow-region-orthodox-places.json : JSON храмов и монастырей}
        {nearby=database/seeders/data/moscow-region-nearby-points.json : JSON парковок, кафе и гостиниц}
        {--no-clean : Не архивировать записи, отсутствующие в новых JSON}
        {--yes : Не запрашивать подтверждение очистки}';

    protected $description = 'Синхронизировать храмы, монастыри и ближайшую инфраструктуру из двух JSON-файлов';

    public function handle(MoscowRegionCatalogSyncService $service): int
    {
        $clean = ! (bool) $this->option('no-clean');

        if ($clean && ! $this->option('yes')) {
            $confirmed = $this->confirm(
                'Будут архивированы старые OSM-объекты и точки, которых нет в новых JSON. Продолжить?',
                false
            );

            if (! $confirmed) {
                $this->warn('Синхронизация отменена. Для импорта без удаления используйте --no-clean.');

                return self::SUCCESS;
            }
        }

        $objectsPath = (string) $this->argument('objects');
        $nearbyPath = (string) $this->argument('nearby');

        $this->info('Запускаю синхронизацию каталога...');
        $this->line('Объекты: '.$objectsPath);
        $this->line('Точки рядом: '.$nearbyPath);
        $this->line('Очистка отсутствующих записей: '.($clean ? 'да' : 'нет'));

        try {
            $result = $service->sync($objectsPath, $nearbyPath, $clean);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Синхронизация завершена. Пакет: '.$result['batch_id']);

        $this->table(
            ['Раздел', 'Создано', 'Обновлено', 'Без изменений', 'Объединено', 'Архивировано', 'Пропущено'],
            [
                [
                    'Объекты',
                    $result['objects']['created'],
                    $result['objects']['updated'],
                    $result['objects']['unchanged'],
                    $result['objects']['duplicates_merged'] + $result['objects']['auxiliary_merged'],
                    $result['objects']['auxiliary_archived'] + $result['objects']['stale_archived'],
                    $result['objects']['trashed_kept'],
                ],
                [
                    'Точки рядом',
                    $result['points']['created'],
                    $result['points']['updated'],
                    $result['points']['unchanged'],
                    $result['points']['input_duplicates'] + $result['points']['duplicate_archived'],
                    $result['points']['stale_archived'] + $result['objects']['generated_points_archived'],
                    $result['points']['invalid'] + $result['points']['missing_object'] + $result['points']['trashed_kept'],
                ],
            ]
        );

        $this->line(
            'JSON объектов: '.$result['source_objects']
            .'; после очистки и дедупликации: '.$result['prepared']['ready']
            .'; приделов исключено: '.$result['prepared']['auxiliary_removed']
            .'; дублей объединено: '.$result['prepared']['nearby_duplicates_merged'].'.'
        );
        $this->line(
            'Дополнительно архивировано повторяющихся точек: '
            .$result['points']['duplicate_archived']
            .'; точек вместе со старыми объектами: '
            .$result['objects']['generated_points_archived'].'.'
        );

        return self::SUCCESS;
    }
}
