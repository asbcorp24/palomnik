<?php

namespace App\Console\Commands;

use App\Services\MonasteryTempleLinkService;
use Illuminate\Console\Command;
use Throwable;

class LinkMonasteryTemples extends Command
{
    protected $signature = 'objects:link-monastery-temples
        {--apply : Сохранить найденные связи в базе}
        {--all : Обрабатывать также объекты, созданные вручную, а не только OSM}
        {--temples-only : Не привязывать часовни}
        {--radius=600 : Максимальное расстояние поиска монастыря в метрах}';

    protected $description = 'Найти храмы на территории монастырей и заполнить parent_object_id';

    public function handle(MonasteryTempleLinkService $service): int
    {
        $apply = (bool) $this->option('apply');
        $osmOnly = ! (bool) $this->option('all');
        $includeChapels = ! (bool) $this->option('temples-only');
        $radius = max(100, min(1500, (int) $this->option('radius')));

        $this->info($apply
            ? 'Привязываю храмы к родительским монастырям...'
            : 'Предварительный поиск связей без изменения базы...');
        $this->line('Объекты: '.($osmOnly ? 'только импортированные OSM' : 'все опубликованные'));
        $this->line('Часовни: '.($includeChapels ? 'учитываются' : 'не учитываются'));
        $this->line('Радиус поиска: '.$radius.' м');

        try {
            $result = $service->link($apply, $osmOnly, $includeChapels, $radius);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Монастырей', 'Проверено храмов', 'Найдено связей', 'Сохранено', 'Неоднозначно', 'Без кандидата'],
            [[
                $result['monasteries'],
                $result['scanned'],
                $result['would_link'],
                $result['linked'],
                $result['ambiguous'],
                $result['no_candidate'],
            ]]
        );

        $rows = [];
        foreach (array_slice($result['samples'], 0, 50) as $sample) {
            $rows[] = [
                $sample['status'] === 'match' ? 'готово' : 'проверить',
                $sample['child_name'],
                $sample['parent_name'],
                $sample['distance_meters'].' м',
                $sample['score'],
                implode(', ', $sample['signals']),
            ];
        }

        if ($rows !== []) {
            $this->newLine();
            $this->table(
                ['Статус', 'Храм/часовня', 'Монастырь', 'Расстояние', 'Оценка', 'Основания'],
                $rows
            );
        }

        if (! $apply && $result['would_link'] > 0) {
            $this->newLine();
            $this->warn('Это был предварительный просмотр. Для сохранения запустите команду с --apply.');
        }

        if ($result['ambiguous'] > 0) {
            $this->warn(
                'Неоднозначные пары не изменялись. Их нужно проверить вручную в админке.'
            );
        }

        return self::SUCCESS;
    }
}
