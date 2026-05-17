<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ─── Auto-complete orders every 5 minutes ───────────────────────────
        // Change ->everyFiveMinutes() to ->everyMinute() if you want faster checks.
        $schedule->command('orders:auto-complete')
            ->everyFiveMinutes()
            ->withoutOverlapping()      // prevents double-run if a previous run is still going
            ->runInBackground()         // doesn't block other scheduled tasks
            ->appendOutputTo(storage_path('logs/auto-complete-orders.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}