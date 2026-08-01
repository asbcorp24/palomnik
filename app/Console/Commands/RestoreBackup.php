<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class RestoreBackup extends Command
{
    protected $signature = 'backup:restore
        {backup : Имя резервной копии}
        {--database : Восстановить базу данных}
        {--files : Восстановить storage/app/public}
        {--force : Подтвердить необратимую операцию}
        {--maintenance : Автоматически включить режим обслуживания}
        {--skip-safety-backup : Не создавать страховочную копию текущего состояния}';

    protected $description = 'Безопасно восстановить базу данных и/или публичные файлы из резервной копии';

    public function handle(BackupService $backups): int
    {
        if (! $this->option('database') && ! $this->option('files')) {
            $this->error('Укажите --database и/или --files.');
            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->error('Восстановление требует явного параметра --force.');
            return self::FAILURE;
        }

        $enteredMaintenance = false;
        if (! app()->isDownForMaintenance()) {
            if (! $this->option('maintenance')) {
                $this->error('Сначала включите режим обслуживания: php artisan down. Либо используйте --maintenance.');
                return self::FAILURE;
            }

            Artisan::call('down', ['--retry' => 60]);
            $enteredMaintenance = true;
        }

        try {
            if (! $this->option('skip-safety-backup')) {
                $this->line('Создаётся страховочная копия текущего состояния...');
                $safety = $backups->create('pre-restore', true, true);
                $this->info('Страховочная копия: '.$safety['name']);
            }

            $result = $backups->restore(
                (string) $this->argument('backup'),
                (bool) $this->option('database'),
                (bool) $this->option('files')
            );

            $this->info('Восстановлена резервная копия: '.$result['backup']);
            $this->line('Компоненты: '.implode(', ', $result['restored']));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Восстановление не выполнено: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($enteredMaintenance) {
                Artisan::call('up');
            }
        }
    }
}
