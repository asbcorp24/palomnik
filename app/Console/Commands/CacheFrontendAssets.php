<?php

namespace App\Console\Commands;

use App\Services\FrontendAssetService;
use Illuminate\Console\Command;
use Throwable;

class CacheFrontendAssets extends Command
{
    protected $signature = 'frontend-assets:cache {--refresh : Загрузить файлы заново}';

    protected $description = 'Загружает CSS, JavaScript и шрифты библиотек в локальный кэш сайта';

    public function handle(FrontendAssetService $assets): int
    {
        $this->info('Кэширование frontend-ресурсов...');

        try {
            $cached = $assets->cacheAll((bool) $this->option('refresh'));
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($cached as $path => $location) {
            $this->line('  <info>✓</info> '.$path.' → '.$location);
        }

        $this->newLine();
        $this->info('Все внешние CSS и JavaScript подготовлены для загрузки с домена сайта.');

        return self::SUCCESS;
    }
}
