<?php

namespace App\Console\Commands;

use App\Services\ObjectDuplicateDetectionService;
use Illuminate\Console\Command;

class ScanObjectDuplicates extends Command
{
    protected $signature = 'objects:scan-duplicates';

    protected $description = 'Пересчитать возможные дубли паломнических объектов';

    public function handle(ObjectDuplicateDetectionService $service): int
    {
        $this->info('Проверяем объекты и формируем кандидатов...');
        $result = $service->scan();

        $this->table(
            ['Объектов', 'Проверено пар', 'Кандидатов', 'Ожидают решения'],
            [[
                $result['objects'],
                $result['pairs_checked'],
                $result['candidates'],
                $result['pending'],
            ]]
        );

        return self::SUCCESS;
    }
}
