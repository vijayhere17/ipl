<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CricketApiService;
use App\Models\CricketMatch;
use App\Models\Contest;
use App\Http\Controllers\Api\ContestController;

class SyncMatchScores extends Command
{
    protected $signature = 'app:sync-match-scores';
    protected $description = 'Sync live scores and update fantasy points';

    public function handle(CricketApiService $api)
    {
        // Get live matches from Sportmonks
        $liveScores = $api->getLiveScores();

        if (empty($liveScores['data'])) {
            $this->info('No live matches right now');
            return;
        }

        foreach ($liveScores['data'] as $apiMatch) {

            $match = CricketMatch::where('api_match_id', $apiMatch['id'])->first();
            if (!$match) continue;

            // Update scores
            $runs = $apiMatch['runs'] ?? [];

            foreach ($runs as $run) {
                if ($run['team_id'] == ($apiMatch['localteam_id'] ?? null)) {
                    $match->team1_score  = $run['score']   ?? 0;
                    $match->team1_wicket = $run['wickets'] ?? 0;
                    $match->team1_over   = $run['overs']   ?? 0;
                }
                if ($run['team_id'] == ($apiMatch['visitorteam_id'] ?? null)) {
                    $match->team2_score  = $run['score']   ?? 0;
                    $match->team2_wicket = $run['wickets'] ?? 0;
                    $match->team2_over   = $run['overs']   ?? 0;
                }
            }

            // Update status
            $statusRaw = $apiMatch['status'] ?? '';
            $isLive    = $apiMatch['live']   ?? false;

            if ($statusRaw === 'Finished') {
                $match->status = 'completed';
            } elseif ($isLive == true || in_array($statusRaw, [
                'In Progress', 'Innings Break', 'Lunch Break',
                'Tea Break', 'Drink Break', 'Stumps',
            ])) {
                $match->status = 'live';
            }

            $match->save();

            // ✅ Update fantasy points for live match
            if ($match->status === 'live') {
                Contest::where('cricket_match_id', $match->id)
                    ->where('is_prize_distributed', 0)
                    ->each(function ($contest) use ($match, $api) {
                        try {
                            app(ContestController::class)
                                ->calculateContestPoints(
                                    $contest->id,
                                    $match->id,
                                    $api
                                );
                        } catch (\Exception $e) {
                            \Log::error('Points calc failed: ' . $e->getMessage());
                        }
                    });
            }

            // ✅ Auto distribute prizes when completed
            if ($match->status === 'completed') {
                Contest::where('cricket_match_id', $match->id)
                    ->where('is_prize_distributed', 0)
                    ->each(function ($contest) use ($match, $api) {
                        try {
                            // Final points calculation
                            app(ContestController::class)
                                ->calculateContestPoints(
                                    $contest->id,
                                    $match->id,
                                    $api
                                );
                            // Distribute
                            app(ContestController::class)
                                ->distributeWinnings($contest->id);
                        } catch (\Exception $e) {
                            \Log::error('Distribution failed: ' . $e->getMessage());
                        }
                    });
            }
        }

        $this->info('✅ Live scores + points synced');
    }
}