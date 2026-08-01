<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class BackupService
{
    public function create(
        string $label = 'manual',
        bool $includeDatabase = true,
        bool $includePublicFiles = true
    ): array {
        if (! config('backup.enabled')) {
            throw new RuntimeException('Резервное копирование отключено настройкой BACKUP_ENABLED.');
        }

        $includeDatabase = $includeDatabase && (bool) config('backup.database.enabled');
        $includePublicFiles = $includePublicFiles && (bool) config('backup.public_files.enabled');

        if (! $includeDatabase && ! $includePublicFiles) {
            throw new RuntimeException('Не выбрана ни одна часть резервной копии.');
        }

        $root = $this->backupRoot();
        File::ensureDirectoryExists($root, 0750, true);
        $this->assertFreeSpace($root);

        $label = Str::slug($label);
        $label = $label !== '' ? mb_substr($label, 0, 80) : 'manual';
        $name = now()->format('Ymd_His').'-'.$label;
        $partialPath = $root.DIRECTORY_SEPARATOR.'.'.$name.'.partial-'.bin2hex(random_bytes(4));
        $finalPath = $root.DIRECTORY_SEPARATOR.$name;

        if (File::exists($finalPath)) {
            $name .= '-'.bin2hex(random_bytes(2));
            $finalPath = $root.DIRECTORY_SEPARATOR.$name;
        }

        File::ensureDirectoryExists($partialPath, 0750, true);

        $manifest = [
            'format_version' => 1,
            'name' => $name,
            'label' => $label,
            'status' => 'creating',
            'created_at' => now()->toAtomString(),
            'completed_at' => null,
            'application' => (string) config('app.name'),
            'environment' => (string) config('app.env'),
            'git_commit' => $this->gitCommit(),
            'database' => null,
            'public_files' => null,
            'total_size_bytes' => 0,
        ];

        $this->writeManifest($partialPath, $manifest);

        try {
            if ($includeDatabase) {
                $manifest['database'] = $this->backupDatabase($partialPath);
                $this->writeManifest($partialPath, $manifest);
            }

            if ($includePublicFiles) {
                $manifest['public_files'] = $this->backupPublicFiles($partialPath);
                $this->writeManifest($partialPath, $manifest);
            }

            $manifest['status'] = 'complete';
            $manifest['completed_at'] = now()->toAtomString();
            $manifest['total_size_bytes'] = (int) data_get($manifest, 'database.size_bytes', 0)
                + (int) data_get($manifest, 'public_files.size_bytes', 0);
            $this->writeManifest($partialPath, $manifest);

            if (! @rename($partialPath, $finalPath)) {
                throw new RuntimeException('Не удалось завершить резервную копию: каталог нельзя переименовать.');
            }

            Log::info('Резервная копия создана.', [
                'backup' => $name,
                'size_bytes' => $manifest['total_size_bytes'],
            ]);

            return $manifest;
        } catch (Throwable $exception) {
            File::deleteDirectory($partialPath);
            Log::error('Ошибка резервного копирования.', [
                'backup' => $name,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    public function prune(?int $keepLast = null, ?int $maxAgeDays = null): array
    {
        $keepLast = max(1, $keepLast ?? (int) config('backup.keep_last', 14));
        $maxAgeDays = max(1, $maxAgeDays ?? (int) config('backup.max_age_days', 30));
        $backups = $this->all();
        $deleted = [];
        $cutoff = now()->subDays($maxAgeDays);

        foreach ($backups as $index => $backup) {
            if ($index < $keepLast) {
                continue;
            }

            $createdAt = isset($backup['created_at']) ? now()->parse($backup['created_at']) : null;
            if ($createdAt && $createdAt->greaterThanOrEqualTo($cutoff)) {
                continue;
            }

            $path = $this->backupPath((string) $backup['name']);
            if (File::deleteDirectory($path)) {
                $deleted[] = $backup['name'];
            }
        }

        foreach (File::directories($this->backupRoot()) as $directory) {
            $basename = basename($directory);
            if (! str_contains($basename, '.partial-')) {
                continue;
            }

            if (File::lastModified($directory) < now()->subDay()->timestamp) {
                File::deleteDirectory($directory);
            }
        }

        return [
            'kept' => count($backups) - count($deleted),
            'deleted' => $deleted,
        ];
    }

    public function all(): array
    {
        $root = $this->backupRoot();
        if (! File::isDirectory($root)) {
            return [];
        }

        $items = [];
        foreach (File::directories($root) as $directory) {
            $manifestPath = $directory.DIRECTORY_SEPARATOR.'manifest.json';
            if (! File::isFile($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) File::get($manifestPath), true);
            if (! is_array($manifest) || ($manifest['status'] ?? null) !== 'complete') {
                continue;
            }

            $manifest['path'] = $directory;
            $items[] = $manifest;
        }

        usort($items, fn (array $a, array $b): int => strcmp(
            (string) ($b['created_at'] ?? ''),
            (string) ($a['created_at'] ?? '')
        ));

        return $items;
    }

    public function latest(): ?array
    {
        return $this->all()[0] ?? null;
    }

    public function restore(string $name, bool $database, bool $publicFiles): array
    {
        if (! $database && ! $publicFiles) {
            throw new RuntimeException('Для восстановления укажите базу данных и/или публичные файлы.');
        }

        $manifest = $this->load($name);
        $this->verify($manifest);
        $restored = [];

        if ($database) {
            if (! is_array($manifest['database'] ?? null)) {
                throw new RuntimeException('В этой резервной копии нет дампа базы данных.');
            }
            $this->restoreDatabase($manifest);
            $restored[] = 'database';
        }

        if ($publicFiles) {
            if (! is_array($manifest['public_files'] ?? null)) {
                throw new RuntimeException('В этой резервной копии нет storage/app/public.');
            }
            $this->restorePublicFiles($manifest);
            $restored[] = 'public_files';
        }

        Log::warning('Выполнено восстановление резервной копии.', [
            'backup' => $name,
            'parts' => $restored,
        ]);

        return [
            'backup' => $name,
            'restored' => $restored,
        ];
    }

    public function load(string $name): array
    {
        $path = $this->backupPath($name);
        $manifestPath = $path.DIRECTORY_SEPARATOR.'manifest.json';
        if (! File::isFile($manifestPath)) {
            throw new RuntimeException('Резервная копия не найдена: '.$name.'.');
        }

        $manifest = json_decode((string) File::get($manifestPath), true);
        if (! is_array($manifest) || ($manifest['status'] ?? null) !== 'complete') {
            throw new RuntimeException('Резервная копия не завершена или повреждена.');
        }
        $manifest['path'] = $path;

        return $manifest;
    }

    public function verify(array $manifest): void
    {
        foreach (['database', 'public_files'] as $section) {
            $item = $manifest[$section] ?? null;
            if (! is_array($item)) {
                continue;
            }

            $file = $manifest['path'].DIRECTORY_SEPARATOR.$item['file'];
            if (! File::isFile($file)) {
                throw new RuntimeException('Отсутствует файл резервной копии: '.$item['file'].'.');
            }

            $hash = hash_file('sha256', $file);
            if (! hash_equals((string) $item['sha256'], (string) $hash)) {
                throw new RuntimeException('Не совпала контрольная сумма файла '.$item['file'].'.');
            }
        }
    }

    private function backupDatabase(string $directory): array
    {
        $connection = (string) config('database.default');
        $config = config('database.connections.'.$connection, []);
        if (($config['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Автоматический дамп поддерживает только MySQL/MariaDB.');
        }

        $binary = (string) config('backup.database.mysqldump_binary', 'mysqldump');
        $credentials = $this->createMysqlCredentials($config);
        $fileName = 'database.sql.gz';
        $target = $directory.DIRECTORY_SEPARATOR.$fileName;
        $gzip = gzopen($target, 'wb9');
        if ($gzip === false) {
            File::delete($credentials);
            throw new RuntimeException('Не удалось создать сжатый файл дампа базы.');
        }

        $stderr = '';
        $command = [
            $binary,
            '--defaults-extra-file='.$credentials,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
        ];
        foreach ((array) config('backup.database.additional_options', []) as $option) {
            $command[] = $option;
        }
        $command[] = (string) ($config['database'] ?? '');

        try {
            $process = new Process($command, base_path(), null, null, (float) config('backup.max_runtime_seconds', 3600));
            $process->run(function (string $type, string $buffer) use ($gzip, &$stderr): void {
                if ($type === Process::ERR) {
                    $stderr .= $buffer;
                    return;
                }
                if (gzwrite($gzip, $buffer) === false) {
                    throw new RuntimeException('Ошибка записи сжатого дампа базы данных.');
                }
            });

            if (! $process->isSuccessful()) {
                throw new RuntimeException('mysqldump завершился с ошибкой: '.trim($stderr ?: $process->getErrorOutput()));
            }
        } finally {
            gzclose($gzip);
            File::delete($credentials);
        }

        return $this->fileMetadata($target, $fileName) + [
            'connection' => $connection,
            'database_name' => (string) ($config['database'] ?? ''),
        ];
    }

    private function backupPublicFiles(string $directory): array
    {
        $source = rtrim((string) config('backup.public_files.path'), DIRECTORY_SEPARATOR);
        if (! File::isDirectory($source)) {
            throw new RuntimeException('Каталог storage/app/public не найден: '.$source.'.');
        }

        $fileName = 'public-files.tar.gz';
        $target = $directory.DIRECTORY_SEPARATOR.$fileName;
        $process = new Process([
            (string) config('backup.public_files.tar_binary', 'tar'),
            '-C', dirname($source),
            '-czf', $target,
            basename($source),
        ], base_path(), null, null, (float) config('backup.max_runtime_seconds', 3600));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Не удалось архивировать storage/app/public: '.trim($process->getErrorOutput()));
        }

        return $this->fileMetadata($target, $fileName) + [
            'source' => $source,
        ];
    }

    private function restoreDatabase(array $manifest): void
    {
        $connection = (string) config('database.default');
        $config = config('database.connections.'.$connection, []);
        if (($config['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Автоматическое восстановление поддерживает только MySQL/MariaDB.');
        }

        $credentials = $this->createMysqlCredentials($config);
        $source = $manifest['path'].DIRECTORY_SEPARATOR.$manifest['database']['file'];
        $input = gzopen($source, 'rb');
        if ($input === false) {
            File::delete($credentials);
            throw new RuntimeException('Не удалось открыть дамп базы данных.');
        }

        try {
            $process = new Process([
                (string) config('backup.database.mysql_binary', 'mysql'),
                '--defaults-extra-file='.$credentials,
                '--default-character-set=utf8mb4',
                (string) ($config['database'] ?? ''),
            ], base_path(), null, null, (float) config('backup.max_runtime_seconds', 3600));
            $process->setInput($input);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('mysql завершился с ошибкой: '.trim($process->getErrorOutput()));
            }
        } finally {
            gzclose($input);
            File::delete($credentials);
        }
    }

    private function restorePublicFiles(array $manifest): void
    {
        $sourceArchive = $manifest['path'].DIRECTORY_SEPARATOR.$manifest['public_files']['file'];
        $target = rtrim((string) config('backup.public_files.path'), DIRECTORY_SEPARATOR);
        $appDirectory = dirname($target);
        $restoreDirectory = $appDirectory.DIRECTORY_SEPARATOR.'.public-restore-'.bin2hex(random_bytes(5));
        $safetyDirectory = $appDirectory.DIRECTORY_SEPARATOR.'.public-before-restore-'.now()->format('Ymd_His');
        File::ensureDirectoryExists($restoreDirectory, 0750, true);

        try {
            $process = new Process([
                (string) config('backup.public_files.tar_binary', 'tar'),
                '-xzf', $sourceArchive,
                '-C', $restoreDirectory,
            ], base_path(), null, null, (float) config('backup.max_runtime_seconds', 3600));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Не удалось распаковать storage/app/public: '.trim($process->getErrorOutput()));
            }

            $restoredPublic = $restoreDirectory.DIRECTORY_SEPARATOR.'public';
            if (! File::isDirectory($restoredPublic)) {
                throw new RuntimeException('Архив публичных файлов имеет неверную структуру.');
            }

            if (File::isDirectory($target) && ! @rename($target, $safetyDirectory)) {
                throw new RuntimeException('Не удалось временно переместить текущий storage/app/public.');
            }

            if (! @rename($restoredPublic, $target)) {
                if (File::isDirectory($safetyDirectory)) {
                    @rename($safetyDirectory, $target);
                }
                throw new RuntimeException('Не удалось установить восстановленный storage/app/public.');
            }

            File::deleteDirectory($safetyDirectory);
        } finally {
            File::deleteDirectory($restoreDirectory);
        }
    }

    private function createMysqlCredentials(array $config): string
    {
        $path = storage_path('app/.mysql-backup-'.bin2hex(random_bytes(8)).'.cnf');
        File::ensureDirectoryExists(dirname($path), 0750, true);

        $lines = [
            '[client]',
            'user='.$this->mysqlValue((string) ($config['username'] ?? '')),
            'password='.$this->mysqlValue((string) ($config['password'] ?? '')),
        ];

        if (filled($config['unix_socket'] ?? null)) {
            $lines[] = 'socket='.$this->mysqlValue((string) $config['unix_socket']);
        } else {
            $lines[] = 'host='.$this->mysqlValue((string) ($config['host'] ?? '127.0.0.1'));
            $lines[] = 'port='.(int) ($config['port'] ?? 3306);
            $lines[] = 'protocol=tcp';
        }

        File::put($path, implode(PHP_EOL, $lines).PHP_EOL, true);
        @chmod($path, 0600);

        return $path;
    }

    private function mysqlValue(string $value): string
    {
        return '"'.str_replace(
            ["\\", '"', "\n", "\r"],
            ["\\\\", '\\"', '\\n', '\\r'],
            $value
        ).'"';
    }

    private function fileMetadata(string $path, string $fileName): array
    {
        if (! File::isFile($path) || File::size($path) <= 0) {
            throw new RuntimeException('Файл резервной копии не создан или пуст: '.$fileName.'.');
        }

        return [
            'file' => $fileName,
            'size_bytes' => File::size($path),
            'sha256' => hash_file('sha256', $path),
        ];
    }

    private function writeManifest(string $directory, array $manifest): void
    {
        File::put(
            $directory.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
            true
        );
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

    private function assertFreeSpace(string $path): void
    {
        $free = @disk_free_space($path);
        if ($free === false) {
            return;
        }

        $required = (int) config('backup.minimum_free_space_mb', 2048) * 1024 * 1024;
        if ($free < $required) {
            throw new RuntimeException('Недостаточно свободного места для резервной копии.');
        }
    }

    private function backupRoot(): string
    {
        return rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
    }

    private function backupPath(string $name): string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $name) || str_contains($name, '..')) {
            throw new RuntimeException('Недопустимое имя резервной копии.');
        }

        return $this->backupRoot().DIRECTORY_SEPARATOR.$name;
    }
}
