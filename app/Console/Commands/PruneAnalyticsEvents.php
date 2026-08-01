<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PruneAnalyticsEvents extends Command
{
    protected $signature = 'analytics:prune {--days=400 : Сколько последних дней сохранить}';

    protected $description = 'Удалить старые события поведенческой аналитики';

    public function handle(): int
    {
        if (! Schema::hasTable('analytics_events')) {
            $this->warn('Таблица analytics_events ещё не создана.');
            return self::SUCCESS;
        }

        $days = max(30, min(3650, (int) $this->option('days')));
        $cutoff = now()->subDays($days);
        $deleted = AnalyticsEvent::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info('Удалено событий: '.$deleted.'. Сохранён период: '.$days.' дней.');

        return self::SUCCESS;
    }
}
