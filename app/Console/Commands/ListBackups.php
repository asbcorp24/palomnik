<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class ListBackups extends Command
{
    protected $signature = 'backup:list {--json : Вывести данные в JSON}';

    protected $description = 'Показать доступные резервные копии';

    public function handle(BackupService $backups): int
    {
        $items = $backups->all();

        if ($this->option('json')) {
            $this->line(json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        if ($items === []) {
            $this->warn('Резервных копий пока нет.');
            return self::SUCCESS;
        }

        $this->table(
            ['Имя', 'Создана', 'Размер', 'База', 'Файлы', 'Git commit'],
            array_map(function (array $item): array {
                return [
                    $item['name'],
                    $item['completed_at'] ?? $item['created_at'] ?? '—',
                    $this->formatBytes((int) ($item['total_size_bytes'] ?? 0)),
                    isset($item['database']) ? 'да' : 'нет',
                    isset($item['public_files']) ? 'да' : 'нет',
                    $item['git_commit'] ? substr((string) $item['git_commit'], 0, 12) : '—',
                ];
            }, $items)
        );

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        $value = max(0, $bytes);
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index === 0 ? 0 : 2, ',', ' ').' '.$units[$index];
    }
}
