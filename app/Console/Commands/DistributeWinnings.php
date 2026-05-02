<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DistributeWinnings extends Command
{
    protected $signature = 'fantasy:distribute-winnings';
    protected $description = 'Distribute winnings to users';

    public function handle()
    {
        $contests = \App\Models\Contest::where('status', 'completed')
            ->where('is_prize_distributed', 0)
            ->get();

        foreach ($contests as $contest) {
            app(\App\Services\WinningsService::class)
                ->distributeWinnings($contest->id);
        }

        $this->info('Winnings distributed successfully');
    }
}