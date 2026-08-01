<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class DeploymentHealth extends Command
{
    protected $signature = 'deploy:health {--json : Вывести только JSON}';

    protected $description = 'Проверить базу, кэш, файловую систему и production-настройки после развёртывания';

    public function handle(): int
    {
        $checks = [];

        $this->capture($checks, 'database', function (): string {
            DB::select('SELECT 1');
            return 'Подключение к базе работает.';
        });

        $this->capture($checks, 'cache', function (): string {
            $key = 'deploy-health:'.Str::random(20);
            Cache::put($key, 'ok', 60);
            if (Cache::get($key) !== 'ok') {
                throw new \RuntimeException('Не удалось прочитать тестовое значение из кэша.');
            }
            Cache::forget($key);
            return 'Запись и чтение кэша работают.';
        });

        $this->capture($checks, 'storage', function (): string {
            $path = storage_path('app/.deploy-health-'.Str::random(16));
            File::put($path, 'ok', true);
            if (File::get($path) !== 'ok') {
                throw new \RuntimeException('Контрольное содержимое storage не совпало.');
            }
            File::delete($path);
            return 'storage доступен для записи.';
        });

        $this->capture($checks, 'bootstrap_cache', function (): string {
            if (! is_writable(base_path('bootstrap/cache'))) {
                throw new \RuntimeException('bootstrap/cache недоступен для записи.');
            }
            return 'bootstrap/cache доступен для записи.';
        });

        $this->capture($checks, 'application', function (): string {
            if ((string) config('app.env') !== 'production') {
                throw new \RuntimeException('APP_ENV не равен production.');
            }
            if ((bool) config('app.debug')) {
                throw new \RuntimeException('APP_DEBUG включён.');
            }
            return 'Production-настройки корректны.';
        });

        $ok = ! collect($checks)->contains(fn (array $check): bool => ! $check['ok']);
        $payload = [
            'ok' => $ok,
            'checked_at' => now()->toAtomString(),
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Проверка', 'Статус', 'Результат'],
                array_map(fn (array $check): array => [
                    $check['name'],
                    $check['ok'] ? 'PASS' : 'FAIL',
                    $check['message'],
                ], $checks)
            );

            $ok
                ? $this->info('Проверка после развёртывания пройдена.')
                : $this->error('Проверка после развёртывания не пройдена.');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function capture(array &$checks, string $name, callable $callback): void
    {
        try {
            $checks[] = [
                'name' => $name,
                'ok' => true,
                'message' => (string) $callback(),
            ];
        } catch (Throwable $exception) {
            $checks[] = [
                'name' => $name,
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }
}
