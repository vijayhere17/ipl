<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CricketApiService;
use App\Models\CricketMatch;
use Carbon\Carbon;

class SyncMatches extends Command
{
    protected $signature = 'app:sync-matches';
    protected $description = 'Sync matches from Sportmonks';

    public function handle(CricketApiService $api)
    {
        $response = $api->getFixtures();

        if (!isset($response['data'])) {
            $this->error('No matches from Sportmonks');
            return;
        }

        foreach ($response['data'] as $match) {

            $statusRaw = $match['status'] ?? '';
            $isLive    = $match['live']   ?? false;

            // ✅ Correct status mapping for Sportmonks
            if ($statusRaw === 'Finished') {
                $status = 'completed';
            } elseif ($isLive == true || in_array($statusRaw, [
                'In Progress',
                'Innings Break',
                'Lunch Break',
                'Tea Break',
                'Drink Break',
                'Stumps',
                'Superover',
                'Delayed',
                'Interrupted',
            ])) {
                $status = 'live';
            } else {
                $status = 'upcoming';
            }

            $team1Name = $match['localteam']['name']  ?? '';
            $team2Name = $match['visitorteam']['name'] ?? '';

            $team1Code = $match['localteam']['code']  ?? '';
            $team2Code = $match['visitorteam']['code'] ?? '';

            $team1Logo = $match['localteam']['image_path']  ?? '';
            $team2Logo = $match['visitorteam']['image_path'] ?? '';

            // Runs
            $runs     = $match['runs'] ?? [];
            $team1Score = $team1Wicket = $team1Over = 0;
            $team2Score = $team2Wicket = $team2Over = 0;

            foreach ($runs as $run) {
                if ($run['team_id'] == ($match['localteam_id'] ?? null)) {
                    $team1Score  = $run['score']   ?? 0;
                    $team1Wicket = $run['wickets'] ?? 0;
                    $team1Over   = $run['overs']   ?? 0;
                }
                if ($run['team_id'] == ($match['visitorteam_id'] ?? null)) {
                    $team2Score  = $run['score']   ?? 0;
                    $team2Wicket = $run['wickets'] ?? 0;
                    $team2Over   = $run['overs']   ?? 0;
                }
            }

            CricketMatch::updateOrCreate(
                ['api_match_id' => $match['id']],
                [
                    'series_name'    => $match['league']['name'] ?? 'IPL',
                    'team_1'         => $team1Name,
                    'team_2'         => $team2Name,
                    'team1_code'     => $team1Code,
                    'team2_code'     => $team2Code,
                    'team1_logo'     => $team1Logo,
                    'team2_logo'     => $team2Logo,
                    'match_start_time' => Carbon::parse($match['starting_at'])->utc(),
                    'status'         => $status,
                    'result_note'    => $match['note'] ?? '',
                    'team1_score'    => $team1Score,
                    'team1_wicket'   => $team1Wicket,
                    'team1_over'     => $team1Over,
                    'team2_score'    => $team2Score,
                    'team2_wicket'   => $team2Wicket,
                    'team2_over'     => $team2Over,
                ]
            );
        }

        $this->info('✅ Matches synced from Sportmonks');
    }
}