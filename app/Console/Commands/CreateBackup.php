<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class CreateBackup extends Command
{
    protected $signature = 'backup:create
        {--label=manual : Метка резервной копии}
        {--database-only : Сохранить только базу данных}
        {--files-only : Сохранить только storage/app/public}
        {--no-prune : Не удалять старые копии после создания}
        {--name-only : Вывести только имя созданной копии}';

    protected $description = 'Создать резервную копию базы данных и storage/app/public';

    public function handle(BackupService $backups): int
    {
        if ($this->option('database-only') && $this->option('files-only')) {
            $this->error('Параметры --database-only и --files-only нельзя использовать одновременно.');
            return self::FAILURE;
        }

        try {
            $manifest = $backups->create(
                (string) $this->option('label'),
                ! $this->option('files-only'),
                ! $this->option('database-only')
            );

            if (! $this->option('no-prune')) {
                $backups->prune();
            }

            if ($this->option('name-only')) {
                $this->line((string) $manifest['name']);
                return self::SUCCESS;
            }

            $this->info('Резервная копия создана: '.$manifest['name']);
            $this->line('Размер: '.$this->formatBytes((int) $manifest['total_size_bytes']));
            $this->line('Git commit: '.($manifest['git_commit'] ?: 'не определён'));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Не удалось создать резервную копию: '.$exception->getMessage());

            return self::FAILURE;
        }
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
