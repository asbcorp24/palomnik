<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        if (config('backup.enabled')) {
            $schedule->command('backup:create --label=daily')
                ->dailyAt((string) config('backup.schedule_time', '01:30'))
                ->withoutOverlapping(240);

            $schedule->command('backup:prune')
                ->weeklyOn(7, '02:20')
                ->withoutOverlapping(60);
        }

        $schedule->command('objects:mark-information-outdated')
            ->dailyAt('02:30')
            ->withoutOverlapping();

        $schedule->command('analytics:prune --days=400')
            ->dailyAt('03:10')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
