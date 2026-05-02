<?php

namespace App\Services;

use App\Models\Contest;
use App\Models\ContestParticipant;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WinningsService
{
    public function distributeWinnings($contest_id)
    {
        $contest = Contest::with('prizes')->find($contest_id);

        if (!$contest || $contest->is_prize_distributed || $contest->status !== 'completed') {
            return false;
        }

        DB::beginTransaction();

        try {

            $participants = ContestParticipant::where('contest_id', $contest_id)
                ->orderByDesc('total_points')
                ->get();

            $rank = 1;
            $prevPoints = null;

            foreach ($participants as $p) {

                if ($prevPoints !== null && $p->total_points == $prevPoints) {
                    $currentRank = $rank - 1;
                } else {
                    $currentRank = $rank;
                }

                $prevPoints = $p->total_points;
                $rank++;

                $winningAmount = 0;

                foreach ($contest->prizes as $prize) {
                    if ($currentRank >= $prize->rank_from && $currentRank <= $prize->rank_to) {
                        $winningAmount = $prize->prize_amount;
                        break;
                    }
                }

                // 💾 SAVE
                $p->update([
                    'rank' => $currentRank,
                    'winning_amount' => $winningAmount
                ]);

                // 💰 CREDIT WALLET
                if ($winningAmount > 0) {
                    $wallet = Wallet::where('user_id', $p->user_id)->first();

                    if ($wallet) {
                        $wallet->winning_balance += $winningAmount;
                        $wallet->save();

                        WalletTransaction::create([
                            'user_id' => $p->user_id,
                            'type' => 'credit',
                            'amount' => $winningAmount,
                            'wallet_type' => 'winning',
                            'reference_id' => $contest->id,
                            'description' => 'Contest Winning #' . $contest->id
                        ]);
                    }
                }
            }

            $contest->update(['is_prize_distributed' => 1]);

            DB::commit();

            return true;

        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }
}