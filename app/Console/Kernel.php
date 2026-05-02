<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
   protected function schedule(Schedule $schedule): void
{
    // 🔄 Sync match statuses
    $schedule->command('app:sync-matches')
        ->everyFiveMinutes()
        ->withoutOverlapping();

    // 📊 Sync live scores
    $schedule->command('app:sync-match-scores')
        ->everyMinute()
        ->withoutOverlapping();

    // 🎯 Update fantasy points
    $schedule->command('app:update-fantasy-points')
        ->everyMinute()
        ->withoutOverlapping();

    // 💰 AUTO DISTRIBUTE WINNINGS (ADD THIS 🔥)
    $schedule->command('fantasy:distribute-winnings')
        ->everyMinute()
        ->withoutOverlapping();
}

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}