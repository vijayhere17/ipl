<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CricketMatch;
use App\Services\CricketApiService;
use Carbon\Carbon;

class UpdateMatchScores extends Command
{
    protected $signature = 'update:match-scores';

    protected $description = 'Update live match scores from API';

   public function handle()
{
    $service = app(\App\Services\CricketApiService::class);

    $this->info('Auto updater started...');

    while (true) {

        try {

            // ✅ USE LIVE SCORES (IMPORTANT 🔥)
            $apiResponse = $service->getLiveScores();

            if (!isset($apiResponse['data'])) {
                $this->error('No API data');
                sleep(10);
                continue;
            }

            foreach ($apiResponse['data'] as $match) {

                // ✅ TIME
                $matchTime = \Carbon\Carbon::parse($match['starting_at']);

                // ✅ STATUS
                $status = 'upcoming';

                if (($match['status'] ?? '') == 'Finished') {
                    $status = 'completed';
                } elseif (($match['status'] ?? '') == 'Live') {
                    $status = 'live';
                }

                // ✅ TEAM NAMES
                $team1_name = $match['localteam']['name'] ?? '';
                $team2_name = $match['visitorteam']['name'] ?? '';

                // ✅ TEAM IDS (VERY IMPORTANT)
                $localTeamId = $match['localteam_id'] ?? null;
                $visitorTeamId = $match['visitorteam_id'] ?? null;

                // ✅ RUNS (SAFE MAPPING 🔥)
                $runs = $match['runs'] ?? [];

                $team1_score = 0;
                $team1_wicket = 0;
                $team1_over = '0.0';

                $team2_score = 0;
                $team2_wicket = 0;
                $team2_over = '0.0';

                foreach ($runs as $run) {

                    if (($run['team_id'] ?? null) == $localTeamId) {
                        $team1_score = $run['score'] ?? 0;
                        $team1_wicket = $run['wickets'] ?? 0;
                        $team1_over = $run['overs'] ?? '0.0';
                    }

                    if (($run['team_id'] ?? null) == $visitorTeamId) {
                        $team2_score = $run['score'] ?? 0;
                        $team2_wicket = $run['wickets'] ?? 0;
                        $team2_over = $run['overs'] ?? '0.0';
                    }
                }

                // ✅ SAVE
                \App\Models\CricketMatch::updateOrCreate(
                    ['api_match_id' => $match['id']],
                    [
                        'series_name' => $match['league']['name'] ?? 'IPL',

                        'team_1' => $team1_name,
                        'team_2' => $team2_name,

                        'match_start_time' => \Carbon\Carbon::parse($match['starting_at'])->utc(),

                        'status' => $status,

                        'team1_score' => $team1_score,
                        'team1_wicket' => $team1_wicket,
                        'team1_over' => $team1_over,

                        'team2_score' => $team2_score,
                        'team2_wicket' => $team2_wicket,
                        'team2_over' => $team2_over,
                    ]
                );

                $this->info("Updated match ID: {$match['id']}");
            }

        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->info('Cycle done: ' . now());

        sleep(10); // every 10 sec
    }
}
}