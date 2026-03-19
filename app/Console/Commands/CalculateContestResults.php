<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contest;
use App\Models\ContestParticipant;
use App\Models\FantasyTeam;
use App\Models\MatchPlayerStat;
use App\Models\FantasyTeamPlayer;

class CalculateContestResults extends Command
{
    protected $signature = 'app:calculate-results';
    protected $description = 'Calculate contest leaderboard and ranks';

    public function handle()
{
    $contests = Contest::where('status', 'completed')
        ->where('is_prize_distributed', false)
        ->get();

    foreach ($contests as $contest) {

        $participants = ContestParticipant::where('contest_id', $contest->id)->get();

        // 🔹 STEP 1: Calculate points
        foreach ($participants as $participant) {

            $team = FantasyTeam::find($participant->fantasy_team_id);
            if (!$team) continue;

            $totalPoints = 0;

            $teamPlayers = FantasyTeamPlayer::where('fantasy_team_id', $team->id)->get();

            foreach ($teamPlayers as $teamPlayer) {

                $stat = MatchPlayerStat::where('cricket_match_id', $team->cricket_match_id)
                    ->where('player_id', $teamPlayer->player_id)
                    ->first();

                if (!$stat) continue;

                $points = $stat->fantasy_points;

                if ($teamPlayer->is_captain) {
                    $points *= 2;
                }

                if ($teamPlayer->is_vice_captain) {
                    $points *= 1.5;
                }

                $totalPoints += $points;
            }

            $team->total_points = $totalPoints;
            $team->save();

            $participant->total_points = $totalPoints;
            $participant->save();
        }

        // 🔹 STEP 2: Ranking
        $rankedParticipants = ContestParticipant::where('contest_id', $contest->id)
            ->orderByDesc('total_points')
            ->get();

        $rank = 1;

        foreach ($rankedParticipants as $rp) {
            $rp->rank = $rank;
            $rp->save();
            $rank++;
        }

        // 🔹 STEP 3: Prize Distribution
        foreach ($rankedParticipants as $rp) {

            $prize = 0;

            if ($rp->rank == 1) {
                $prize = $contest->prize_pool * 0.60;
            } elseif ($rp->rank == 2) {
                $prize = $contest->prize_pool * 0.30;
            } elseif ($rp->rank == 3) {
                $prize = $contest->prize_pool * 0.10;
            }

            if ($prize > 0) {

                $wallet = \App\Models\Wallet::where('user_id', $rp->user_id)->first();
                if (!$wallet) continue;

                $wallet->winning_balance += $prize;
                $wallet->save();

                \App\Models\WalletTransaction::create([
                    'user_id' => $rp->user_id,
                    'type' => 'winning_credit',
                    'wallet_type' => 'winning',
                    'amount' => $prize,
                    'reference_id' => $contest->id,
                    'description' => 'Contest winning credited'
                ]);
            }
        }

        // 🔹 STEP 4: Mark contest as distributed (VERY IMPORTANT)
        $contest->is_prize_distributed = true;
        $contest->save();
    }

    $this->info('Contest results calculated successfully');
}
}