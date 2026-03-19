<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CricketApiService;
use App\Models\CricketMatch;
use App\Models\Player;
use App\Models\MatchPlayerStat;
use App\Models\Contest;

class SyncMatchScores extends Command
{
    protected $signature = 'app:sync-match-scores';
    protected $description = 'Sync match scores and fantasy points from CricAPI';

    public function handle(CricketApiService $api)
    {
        // 🔥 STEP 1: Get all live matches from API
        $allMatches = $api->getCurrentMatches();

        if (!isset($allMatches['data'])) {
            $this->error('No matches from API');
            return;
        }

        // 🔥 STEP 2: Loop DB matches
        $matches = CricketMatch::whereIn('status', ['live', 'upcoming'])->get();

        foreach ($matches as $match) {

            // 🔥 FIND MATCH FROM API
            $apiMatch = collect($allMatches['data'])
                ->firstWhere('id', $match->api_match_id);

            if (!$apiMatch) continue;

            /*
            |------------------------------------------
            | 1️⃣ UPDATE SCORE (MAIN FIX)
            |------------------------------------------
            */
            $score = $apiMatch['score'] ?? [];

            if (isset($score[0])) {
                $match->team1_score = $score[0]['r'] ?? 0;
                $match->team1_wicket = $score[0]['w'] ?? 0;
                $match->team1_over = $score[0]['o'] ?? 0;
            }

            if (isset($score[1])) {
                $match->team2_score = $score[1]['r'] ?? 0;
                $match->team2_wicket = $score[1]['w'] ?? 0;
                $match->team2_over = $score[1]['o'] ?? 0;
            }

            /*
            |------------------------------------------
            | 2️⃣ UPDATE STATUS
            |------------------------------------------
            */
            $apiStatus = strtolower($apiMatch['status'] ?? '');

            if (str_contains($apiStatus, 'won') || str_contains($apiStatus, 'match over')) {

                $match->status = 'completed';

                // Complete contests
                Contest::where('cricket_match_id', $match->id)
                    ->where('status', '!=', 'completed')
                    ->update(['status' => 'completed']);

                // Calculate results
                \Artisan::call('app:calculate-results');

            } elseif ($apiMatch['matchStarted']) {
                $match->status = 'live';
            } else {
                $match->status = 'upcoming';
            }

            $match->save();

            /*
            |------------------------------------------
            | 3️⃣ FANTASY POINTS (FROM SCORECARD)
            |------------------------------------------
            */
            $scorecard = $api->getScoreCard($match->api_match_id);

            if (!isset($scorecard['data']['scorecard'])) continue;

            foreach ($scorecard['data']['scorecard'] as $inning) {

                foreach ($inning['batting'] ?? [] as $bat) {

                    $playerName = $bat['batsman']['name'] ?? null;

                    if (!$playerName) continue;

                    $player = Player::where('name', $playerName)->first();
                    if (!$player) continue;

                    $runs = (int)($bat['r'] ?? 0);
                    $fours = (int)($bat['4s'] ?? 0);
                    $sixes = (int)($bat['6s'] ?? 0);

                    /*
                    |------------------------------------------
                    | SIMPLE FANTASY RULE (YOU CAN UPGRADE)
                    |------------------------------------------
                    */
                    $points = $runs + ($fours * 1) + ($sixes * 2);

                    MatchPlayerStat::updateOrCreate(
                        [
                            'cricket_match_id' => $match->id,
                            'player_id' => $player->id
                        ],
                        [
                            'runs' => $runs,
                            'fours' => $fours,
                            'sixes' => $sixes,
                            'fantasy_points' => $points
                        ]
                    );
                }
            }
        }

        $this->info('✅ Match scores + fantasy points synced successfully');
    }
}