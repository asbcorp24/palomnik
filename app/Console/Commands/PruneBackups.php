<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class PruneBackups extends Command
{
    protected $signature = 'backup:prune
        {--keep= : Минимальное число последних копий}
        {--days= : Удалять более старые копии после сохранения последних}';

    protected $description = 'Удалить старые резервные копии по политике хранения';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->prune(
                $this->option('keep') !== null ? (int) $this->option('keep') : null,
                $this->option('days') !== null ? (int) $this->option('days') : null
            );

            $this->info('Удалено резервных копий: '.count($result['deleted']).'.');
            $this->line('Оставлено: '.$result['kept'].'.');

            foreach ($result['deleted'] as $name) {
                $this->line('Удалена: '.$name);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Не удалось очистить резервные копии: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
