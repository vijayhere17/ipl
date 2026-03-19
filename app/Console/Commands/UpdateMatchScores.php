<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CricketMatch;
use App\Services\CricketApiService;

class UpdateMatchScores extends Command
{
    protected $signature = 'update:match-scores';

    protected $description = 'Update live match scores from CricAPI';

    public function handle()
{
    $service = app(\App\Services\CricketApiService::class);

    $matches = \App\Models\CricketMatch::whereIn('status',['live','completed'])->get();

    foreach ($matches as $match) {

        $info = $service->getMatchInfo($match->api_match_id);

        if(!isset($info['data']['score'])){
            continue;
        }

        $scores = $info['data']['score'];

        $team1Runs = 0;
        $team1Wickets = 0;
        $team1Overs = 0;

        $team2Runs = 0;
        $team2Wickets = 0;
        $team2Overs = 0;

        foreach ($scores as $index => $s) {

            if ($index % 2 == 0) {
                $team1Runs += $s['r'] ?? 0;
                $team1Wickets += $s['w'] ?? 0;
                $team1Overs += $s['o'] ?? 0;
            } else {
                $team2Runs += $s['r'] ?? 0;
                $team2Wickets += $s['w'] ?? 0;
                $team2Overs += $s['o'] ?? 0;
            }
        }

        $match->update([
            'team1_score' => $team1Runs,
            'team1_wicket' => $team1Wickets,
            'team1_over' => $team1Overs,
            'team2_score' => $team2Runs,
            'team2_wicket' => $team2Wickets,
            'team2_over' => $team2Overs
        ]);
    }

    $this->info('Scores updated successfully');
}
}