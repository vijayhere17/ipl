<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateFantasyPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-fantasy-points';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
   public function handle()
{
    $matches = \App\Models\CricketMatch::where('status', 'live')->get();

    foreach ($matches as $match) {
        app(\App\Services\FantasyPointService::class)
            ->updateMatchPoints($match->id);
    }
}
}
