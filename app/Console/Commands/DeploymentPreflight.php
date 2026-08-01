<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

class DeploymentPreflight extends Command
{
    protected $signature = 'deploy:preflight
        {--strict : Считать критические предупреждения ошибками}
        {--allow-destructive : Разрешить потенциально разрушающие миграции}
        {--require-backup : Требовать свежую завершённую резервную копию}
        {--json : Вывести только JSON}';

    protected $description = 'Проверить окружение и ожидающие миграции перед production-развёртыванием';

    private array $checks = [];

    public function handle(BackupService $backups): int
    {
        $strict = (bool) $this->option('strict');

        $this->checkEnvironment($strict);
        $this->checkWritableDirectories();
        $this->checkDatabase();
        $pending = $this->checkMigrations($strict, (bool) $this->option('allow-destructive'));
        $this->checkBackupTools();
        $this->checkFreeSpace();

        if ($this->option('require-backup')) {
            $this->checkRecentBackup($backups);
        }

        $failed = collect($this->checks)->contains(fn (array $check): bool => $check['status'] === 'fail');
        $payload = [
            'ok' => ! $failed,
            'environment' => (string) config('app.env'),
            'git_commit' => $this->gitCommit(),
            'pending_migrations_count' => count($pending),
            'pending_migrations' => array_values($pending),
            'checks' => $this->checks,
            'checked_at' => now()->toAtomString(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Проверка', 'Статус', 'Результат'],
                array_map(fn (array $check): array => [
                    $check['name'],
                    strtoupper($check['status']),
                    $check['message'],
                ], $this->checks)
            );

            $failed
                ? $this->error('Предварительная проверка не пройдена.')
                : $this->info('Предварительная проверка пройдена.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function checkEnvironment(bool $strict): void
    {
        $environment = (string) config('app.env');
        $this->add(
            'APP_ENV',
            $environment === 'production' ? 'pass' : ($strict ? 'fail' : 'warn'),
            $environment === 'production'
                ? 'production'
                : 'Текущее окружение: '.$environment.'. Для production ожидается APP_ENV=production.'
        );

        $debug = (bool) config('app.debug');
        $this->add(
            'APP_DEBUG',
            ! $debug ? 'pass' : ($strict ? 'fail' : 'warn'),
            $debug ? 'APP_DEBUG должен быть false на production.' : 'Отладка отключена.'
        );

        $key = (string) config('app.key');
        $this->add(
            'APP_KEY',
            $key !== '' ? 'pass' : 'fail',
            $key !== '' ? 'Ключ приложения задан.' : 'APP_KEY не задан.'
        );
    }

    private function checkWritableDirectories(): void
    {
        foreach ([
            storage_path(),
            storage_path('app'),
            storage_path('framework'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ] as $path) {
            File::ensureDirectoryExists($path, 0750, true);
            $this->add(
                'Запись: '.$path,
                is_writable($path) ? 'pass' : 'fail',
                is_writable($path) ? 'Каталог доступен для записи.' : 'Нет прав на запись.'
            );
        }
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $driver = DB::connection()->getDriverName();
            $this->add('Подключение к базе', 'pass', 'Соединение установлено, драйвер: '.$driver.'.');

            $this->add(
                'Драйвер резервного копирования',
                $driver === 'mysql' ? 'pass' : 'fail',
                $driver === 'mysql'
                    ? 'MySQL/MariaDB поддерживается.'
                    : 'Автоматический дамп настроен только для MySQL/MariaDB.'
            );
        } catch (Throwable $exception) {
            $this->add('Подключение к базе', 'fail', $exception->getMessage());
        }
    }

    private function checkMigrations(bool $strict, bool $allowDestructive): array
    {
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $ran = Schema::hasTable('migrations')
                ? $migrator->getRepository()->getRan()
                : [];
            $pending = array_values(array_diff(array_keys($files), $ran));

            $this->add(
                'Ожидающие миграции',
                'pass',
                $pending === [] ? 'Нет ожидающих миграций.' : 'Ожидают выполнения: '.count($pending).'.'
            );

            foreach ($pending as $migration) {
                $path = $files[$migration] ?? null;
                if (! $path || ! File::isFile($path)) {
                    $this->add('Миграция '.$migration, 'fail', 'Файл миграции не найден.');
                    continue;
                }

                $source = (string) File::get($path);
                $hasDown = preg_match('/function\s+down\s*\(/i', $source) === 1;
                $this->add(
                    'Откат '.$migration,
                    $hasDown ? 'pass' : ($strict ? 'fail' : 'warn'),
                    $hasDown ? 'Метод down() найден.' : 'Метод down() не найден.'
                );

                $dangerous = $this->destructiveOperations($source);
                if ($dangerous !== []) {
                    $status = $allowDestructive ? 'warn' : 'fail';
                    $this->add(
                        'Риск '.$migration,
                        $status,
                        'Обнаружены потенциально разрушающие операции: '.implode(', ', $dangerous)
                            .($allowDestructive ? '. Разрешено явным флагом.' : '.')
                    );
                }
            }

            return $pending;
        } catch (Throwable $exception) {
            $this->add('Проверка миграций', 'fail', $exception->getMessage());
            return [];
        }
    }

    private function destructiveOperations(string $source): array
    {
        $patterns = [
            'dropIfExists' => '/dropIfExists\s*\(/i',
            'drop table' => '/->\s*drop\s*\(/i',
            'dropColumn' => '/dropColumn\s*\(/i',
            'dropForeign' => '/dropForeign\s*\(/i',
            'renameColumn' => '/renameColumn\s*\(/i',
            'truncate' => '/truncate\s*\(/i',
            'change column' => '/->\s*change\s*\(/i',
            'raw SQL' => '/DB::(?:statement|unprepared)\s*\(/i',
        ];

        $found = [];
        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $source) === 1) {
                $found[] = $label;
            }
        }

        return $found;
    }

    private function checkBackupTools(): void
    {
        if (config('backup.database.enabled')) {
            $this->checkBinary('mysqldump', (string) config('backup.database.mysqldump_binary'), '--version');
            $this->checkBinary('mysql', (string) config('backup.database.mysql_binary'), '--version');
        }

        if (config('backup.public_files.enabled')) {
            $this->checkBinary('tar', (string) config('backup.public_files.tar_binary'), '--version');
        }
    }

    private function checkBinary(string $name, string $binary, string $versionArgument): void
    {
        try {
            $process = new Process([$binary, $versionArgument], base_path(), null, null, 10);
            $process->run();
            $this->add(
                'Команда '.$name,
                $process->isSuccessful() ? 'pass' : 'fail',
                $process->isSuccessful()
                    ? trim(strtok($process->getOutput() ?: $process->getErrorOutput(), PHP_EOL))
                    : trim($process->getErrorOutput())
            );
        } catch (Throwable $exception) {
            $this->add('Команда '.$name, 'fail', $exception->getMessage());
        }
    }

    private function checkFreeSpace(): void
    {
        $path = (string) config('backup.path');
        File::ensureDirectoryExists($path, 0750, true);
        $free = @disk_free_space($path);

        if ($free === false) {
            $this->add('Свободное место', 'warn', 'Не удалось определить свободное место.');
            return;
        }

        $requiredMb = (int) config('backup.minimum_free_space_mb', 2048);
        $freeMb = (int) floor($free / 1024 / 1024);
        $this->add(
            'Свободное место',
            $freeMb >= $requiredMb ? 'pass' : 'fail',
            'Свободно '.$freeMb.' МБ, требуется минимум '.$requiredMb.' МБ.'
        );
    }

    private function checkRecentBackup(BackupService $backups): void
    {
        try {
            $latest = $backups->latest();
            if (! $latest) {
                $this->add('Свежая резервная копия', 'fail', 'Завершённых резервных копий нет.');
                return;
            }

            $completedAt = Carbon::parse($latest['completed_at'] ?? $latest['created_at']);
            $age = $completedAt->diffInMinutes(now());
            $maxAge = (int) config('backup.deployment_max_age_minutes', 60);
            $this->add(
                'Свежая резервная копия',
                $age <= $maxAge ? 'pass' : 'fail',
                'Последняя копия '.$latest['name'].' создана '.$age.' мин. назад; допустимо '.$maxAge.' мин.'
            );
        } catch (Throwable $exception) {
            $this->add('Свежая резервная копия', 'fail', $exception->getMessage());
        }
    }

    private function gitCommit(): ?string
    {
        try {
            $process = new Process(['git', 'rev-parse', 'HEAD'], base_path(), null, null, 5);
            $process->run();
            return $process->isSuccessful() ? trim($process->getOutput()) : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function add(string $name, string $status, string $message): void
    {
        $this->checks[] = compact('name', 'status', 'message');
    }
}
