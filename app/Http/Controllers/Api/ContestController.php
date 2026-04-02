<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contest;
use App\Models\ContestParticipant;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\CricketMatch;
use Illuminate\Support\Facades\DB;
use App\Services\CricketApiService;
use App\Models\Player;

class ContestController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | Join Public Contest
    |--------------------------------------------------------------------------
    */

    private function calculateWinners($slots)
{
    return ceil($slots / 5);
}

private function generateDistribution($winners)
{
    $distribution = [];

    // 🔥 Base structure (better UX)
    if ($winners == 1) {
        return [
            ['rank' => 1, 'percent' => 65]
        ];
    }

    if ($winners == 2) {
        return [
            ['rank' => 1, 'percent' => 40],
            ['rank' => 2, 'percent' => 25],
        ];
    }

    if ($winners == 3) {
        return [
            ['rank' => 1, 'percent' => 35],
            ['rank' => 2, 'percent' => 20],
            ['rank' => 3, 'percent' => 10],
        ];
    }

    // 🔥 For 4+ winners
    $base = [30, 20, 15]; // Top 3

    $remaining = 65 - array_sum($base);
    $extraWinners = $winners - 3;

    $extraPercent = $extraWinners > 0 ? $remaining / $extraWinners : 0;

    // Top 3
    foreach ($base as $index => $percent) {
        $distribution[] = [
            'rank' => $index + 1,
            'percent' => $percent
        ];
    }

    // Remaining winners
    for ($i = 4; $i <= $winners; $i++) {
        $distribution[] = [
            'rank' => $i,
            'percent' => round($extraPercent, 2)
        ];
    }

    return $distribution;
}

public function createPrivateContest(Request $request)
{
    $request->validate([
        'cricket_match_id' => 'required',
        'name' => 'required|string|max:100',
        'entry_fee' => 'required|numeric|min:1',
        'total_slots' => 'required|integer|min:2|max:100'
    ]);

    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'User not authenticated'
        ]);
    }

    // ✅ FIX: UUID → DB ID
    $match = \App\Models\CricketMatch::where('api_match_id', $request->cricket_match_id)->first();

    if (!$match) {
        return response()->json([
            'status' => false,
            'message' => 'Match not found'
        ]);
    }

    // ✅ Prize pool calculation
    $totalCollection = $request->entry_fee * $request->total_slots;

    $platformFee = round($totalCollection * 0.15, 2);   // 15%
    $creatorBonus = round($totalCollection * 0.20, 2);  // 20%
    $prizePool = $totalCollection - ($platformFee + $creatorBonus);

    // ✅ Winner logic (dynamic)
    $totalWinners = max(1, floor($request->total_slots / 5));

    // ✅ Generate private code
    $privateCode = strtoupper(substr(md5(uniqid()), 0, 6));

    // ✅ Create contest
    $contest = \App\Models\Contest::create([
        'cricket_match_id' => $match->id, // ✅ FIXED
        'name' => $request->name,
        'contest_type' => 'private',
        'private_code' => $privateCode,
        'entry_fee' => $request->entry_fee,
        'total_slots' => $request->total_slots,
        'filled_slots' => 1, // creator joined
        'total_winners' => $totalWinners,
        'max_team_per_user' => 1,
        'prize_pool' => $prizePool,
        'platform_fee' => $platformFee,
        'status' => 'upcoming',
        'is_prize_distributed' => 0
    ]);

    // ✅ Prize distribution
    $prizes = [];

    if ($totalWinners == 1) {
        $prizes[] = ['rank' => 1, 'amount' => $prizePool];
    } else {
        $distribution = [35, 20, 10]; // first 3

        for ($i = 0; $i < $totalWinners; $i++) {

            if ($i < 3) {
                $amount = ($distribution[$i] / 100) * $prizePool;
            } else {
                $remainingPercent = 100 - array_sum($distribution);
                $remainingWinners = $totalWinners - 3;
                $amount = ($remainingPercent / $remainingWinners / 100) * $prizePool;
            }

            $prizes[] = [
                'rank' => $i + 1,
                'amount' => round($amount, 2)
            ];
        }
    }

    foreach ($prizes as $p) {
       \App\Models\ContestPrize::create([
    'contest_id' => $contest->id,
    'rank_from' => $p['rank'],
    'rank_to' => $p['rank'],
        'prize_amount' => $p['amount']   // ✅ CORRECT
]);
    }

    // ✅ Creator auto join (IMPORTANT)
    \App\Models\ContestParticipant::create([
        'contest_id' => $contest->id,
        'user_id' => $user->id,
        'fantasy_team_id' => null, // user selects later
        'total_points' => 0
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Private contest created successfully',
        'data' => [
            'contest_id' => $contest->id,
            'private_code' => $privateCode,
            'prize_pool' => $prizePool,
            'platform_fee' => $platformFee,
            'creator_bonus' => $creatorBonus,
            'total_winners' => $totalWinners
        ]
    ]);
}

public function joinPrivateContest(Request $request)
{
    $request->validate([
        'private_code' => 'required',
        'team_id' => 'required|exists:fantasy_teams,id'
    ]);

    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'User not authenticated'
        ]);
    }

    // ✅ STEP 1: FIND CONTEST
    $contest = Contest::where('private_code', $request->private_code)->first();

    if (!$contest) {
        return response()->json([
            'status' => false,
            'message' => 'Invalid contest code'
        ]);
    }

    // ❌ STEP 2: CHECK FULL
    if ($contest->filled_slots >= $contest->total_slots) {
        return response()->json([
            'status' => false,
            'message' => 'Contest full'
        ]);
    }

    // ❌ STEP 3: CHECK ALREADY JOINED
    $alreadyJoined = ContestParticipant::where('contest_id', $contest->id)
        ->where('user_id', $user->id)
        ->exists();

    if ($alreadyJoined) {
        return response()->json([
            'status' => false,
            'message' => 'Already joined this contest'
        ]);
    }

    // ✅ STEP 4: ADD PARTICIPANT
    ContestParticipant::create([
        'contest_id' => $contest->id,
        'user_id' => $user->id,
        'fantasy_team_id' => $request->team_id,
        'total_points' => 0
    ]);

    // ✅ STEP 5: UPDATE SLOTS
    $contest->increment('filled_slots');

    return response()->json([
        'status' => true,
        'message' => 'Joined contest successfully',
        'data' => [
            'contest_id' => $contest->id,
            'filled_slots' => $contest->filled_slots,
            'total_slots' => $contest->total_slots
        ]
    ]);
}
    public function join(Request $request)
{
    $request->validate([
        'contest_id' => 'required|exists:contests,id',
        'fantasy_team_id' => 'required|exists:fantasy_teams,id',
    ]);

    $user = auth()->user();

    $contest = Contest::find($request->contest_id);

    if(!$contest){
        return response()->json([
            'status'=>false,
            'message'=>'Contest not found'
        ]);
    }

    if($contest->filled_slots >= $contest->total_slots){
        return response()->json([
            'status'=>false,
            'message'=>'Contest full'
        ]);
    }

    // prevent same team joining twice
    $alreadyJoined = ContestParticipant::where([
        'contest_id'=>$contest->id,
        'fantasy_team_id'=>$request->fantasy_team_id
    ])->exists();

    if($alreadyJoined){
        return response()->json([
            'status'=>false,
            'message'=>'Team already joined this contest'
        ]);
    }

    DB::beginTransaction();

    try{

        $wallet = Wallet::where('user_id',$user->id)->first();

        if($wallet->deposit_balance < $contest->entry_fee){
            return response()->json([
                'status'=>false,
                'message'=>'Insufficient wallet balance'
            ]);
        }

        // deduct wallet
        $wallet->deposit_balance -= $contest->entry_fee;
        $wallet->save();

        // add participant
        ContestParticipant::create([
            'contest_id'=>$contest->id,
            'user_id'=>$user->id,
            'fantasy_team_id'=>$request->fantasy_team_id,
            'points'=>0,
            'rank'=>null
        ]);

        // increase slot
        $contest->increment('filled_slots');

        DB::commit();

        return response()->json([
            'status'=>true,
            'message'=>'Contest joined successfully'
        ]);

    }catch(\Exception $e){

        DB::rollBack();

        return response()->json([
            'status'=>false,
            'message'=>$e->getMessage()
        ]);
    }
}


public function calculatePlayerPoints($match_id, CricketApiService $service)
{
    $scorecard = $service->getScoreCard($match_id);
    $data = $scorecard['data'] ?? [];

    $playersPoints = [];

    foreach ($data['scorecard'] ?? [] as $inning) {

        // 🏏 BATTING
        foreach ($inning['batting'] ?? [] as $b) {

            $name = strtolower(trim($b['batsman']['name'] ?? ''));

            $player = Player::whereRaw('LOWER(name) = ?', [$name])->first();
            if (!$player) continue;

            $runs = $b['r'] ?? 0;
            $balls = $b['b'] ?? 0;
            $fours = $b['4s'] ?? 0;
            $sixes = $b['6s'] ?? 0;

            $points = 0;

            $points += $runs;
            $points += $fours * 1;
            $points += $sixes * 2;

            if ($runs >= 100) $points += 16;
            elseif ($runs >= 75) $points += 12;
            elseif ($runs >= 50) $points += 8;
            elseif ($runs >= 25) $points += 4;

            if ($balls >= 10) {
                $sr = ($runs / $balls) * 100;

                if ($sr >= 170) $points += 6;
                elseif ($sr >= 150) $points += 4;
                elseif ($sr >= 130) $points += 2;
                elseif ($sr < 50) $points -= 6;
                elseif ($sr < 60) $points -= 4;
                elseif ($sr < 70) $points -= 2;
            }

            if ($runs == 0 && $balls > 0) {
                $points -= 2;
            }

            $playersPoints[$player->id] = ($playersPoints[$player->id] ?? 0) + $points;
        }

        // 🎯 BOWLING
        foreach ($inning['bowling'] ?? [] as $b) {

            $name = strtolower(trim($b['bowler']['name'] ?? ''));

            $player = Player::whereRaw('LOWER(name) = ?', [$name])->first();
            if (!$player) continue;

            $wickets = $b['w'] ?? 0;
            $maidens = $b['m'] ?? 0;
            $runs = $b['r'] ?? 0;
            $overs = $b['o'] ?? 0;

            $points = 0;

            $points += $wickets * 25;

            if ($wickets >= 5) $points += 16;
            elseif ($wickets >= 4) $points += 8;
            elseif ($wickets >= 3) $points += 4;

            $points += $maidens * 12;

            if ($overs >= 2) {
                $eco = $runs / $overs;

                if ($eco < 5) $points += 6;
                elseif ($eco < 6) $points += 4;
                elseif ($eco < 7) $points += 2;
                elseif ($eco > 10) $points -= 6;
                elseif ($eco > 9) $points -= 4;
                elseif ($eco > 8) $points -= 2;
            }

            $playersPoints[$player->id] = ($playersPoints[$player->id] ?? 0) + $points;
        }
    }

    // ✅ SAVE TO DB
    foreach ($playersPoints as $playerId => $points) {
        Player::where('id', $playerId)->update(['points' => $points]);
    }

    return $playersPoints;
}

public function calculateContestPoints($contest_id, $match_id, CricketApiService $service)
{
    $playerPoints = $this->calculatePlayerPoints($match_id, $service);

    $participants = ContestParticipant::where('contest_id', $contest_id)->get();

    foreach ($participants as $p) {

        $teamPlayers = \App\Models\FantasyTeamPlayer::with('player')
            ->where('fantasy_team_id', $p->fantasy_team_id)
            ->get();

        $total = 0;

        foreach ($teamPlayers as $tp) {

            $apiId = $tp->player->api_player_id;

            $points = $playerPoints[$apiId] ?? 0;

            // Captain
            if ($tp->is_captain) {
                $points *= 2;
            }

            // Vice Captain
            elseif ($tp->is_vice_captain) {
                $points *= 1.5;
            }

            $total += $points;
        }

        $p->update([
            'total_points' => $total
        ]);
    }

    return response()->json([
        'status' => true,
        'message' => 'Points updated successfully'
    ]);
}

public function leaderboard($contest_id)
{
    $participants = ContestParticipant::with('user')
        ->where('contest_id', $contest_id)
        ->orderByDesc('total_points')
        ->get();

    $rank = 1;
    $prevPoints = null;
    $sameRank = 1;

    $data = [];

    foreach ($participants as $index => $p) {

        if ($prevPoints !== null && $p->total_points == $prevPoints) {
            $currentRank = $sameRank;
        } else {
            $currentRank = $rank;
            $sameRank = $rank;
        }

        $prevPoints = $p->total_points;
        $rank++;

        $data[] = [
            'rank' => $currentRank,
            'user_name' => $p->user->name ?? 'User',
            'team_id' => $p->fantasy_team_id,
            'points' => $p->total_points
        ];
    }

    return response()->json([
        'status' => true,
        'data' => $data
    ]);
}


public function testPoints($match_id, CricketApiService $service)
{
    $points = $this->calculatePlayerPoints($match_id, $service);

    return response()->json([
        'status' => true,
        'data' => $points
    ]);
}
    /*
    |--------------------------------------------------------------------------
    | Create Private Contest
    |--------------------------------------------------------------------------
    */

    


    /*
    |--------------------------------------------------------------------------
    | Get Contests For Match
    |--------------------------------------------------------------------------
    */

public function getMatchContests($matchId)
{
    $match = CricketMatch::where('api_match_id', $matchId)->first();

    if (!$match) {
        return response()->json([
            'status' => false,
            'message' => 'Match not found'
        ]);
    }

    $userId = auth()->id();

    $contests = Contest::where('cricket_match_id', $match->id)
        ->where('status', 'upcoming')
        ->with('prizes') // load prize distribution
        ->get()
        ->map(function ($contest) use ($match, $userId) {

            $userJoined = ContestParticipant::where('contest_id', $contest->id)
                ->where('user_id', $userId)
                ->exists();

            $progress = $contest->total_slots > 0
                ? round(($contest->filled_slots / $contest->total_slots) * 100, 2)
                : 0;

            return [

                'contest_id' => $contest->id,

                'contest_name' => $contest->name,

                'contest_badge' => $contest->contest_badge,

                'entry_fee' => $contest->entry_fee,

                'prize_pool' => $contest->prize_pool,

                'first_prize' => $contest->first_prize,

                'total_slots' => $contest->total_slots,

                'filled_slots' => $contest->filled_slots,

                'spots_left' => $contest->total_slots - $contest->filled_slots,

                'progress_percent' => $progress,

                'max_team_per_user' => $contest->max_team_per_user,

                'total_winners' => $contest->total_winners,

                'is_guaranteed' => $contest->is_guaranteed,

                'contest_type' => $contest->contest_type,

                'user_joined' => $userJoined,

                /*
                |--------------------------------------------------------------------------
                | Prize Breakup
                |--------------------------------------------------------------------------
                */

                'prize_breakup' => $contest->prizes->map(function ($prize) {
                    return [
                        'rank_from' => $prize->rank_from,
                        'rank_to' => $prize->rank_to,
                        'prize_amount' => $prize->prize_amount,
                        'extra_prize' => $prize->extra_prize
                    ];
                }),

                'match' => [
                    'team1' => $match->team_1,
                    'team2' => $match->team_2,
                    'series' => $match->series_name,
                    'match_time' => $match->match_start_time
                ]

            ];
        });

    return response()->json([
        'status' => true,
        'data' => $contests
    ]);
}


    /*
    |--------------------------------------------------------------------------
    | Join Private Contest
    |--------------------------------------------------------------------------
    */

    public function joinPrivateByCode(Request $request)
    {

        $request->validate([
            'private_code' => 'required|string',
            'fantasy_team_id' => 'required|exists:fantasy_teams,id'
        ]);

        $user = auth()->user();

        $contest = Contest::where('private_code', $request->private_code)
            ->where('contest_type', 'private')
            ->where('status', 'upcoming')
            ->first();

        if (!$contest) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid private contest code'
            ], 400);
        }

        return $this->join(new Request([
            'contest_id' => $contest->id,
            'fantasy_team_id' => $request->fantasy_team_id
        ]));
    }


    /*
    |--------------------------------------------------------------------------
    | Contest Leaderboard
    |--------------------------------------------------------------------------
    */

    // public function leaderboard($contestId)
    // {

    //     $contest = Contest::find($contestId);

    //     if (!$contest) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Contest not found'
    //         ], 404);
    //     }

    //     $participants = ContestParticipant::where('contest_id', $contestId)
    //         ->with(['user', 'fantasyTeam'])
    //         ->orderBy('rank')
    //         ->get()
    //         ->map(function ($participant) {

    //             return [

    //                 'rank' => $participant->rank,
    //                 'team_name' => $participant->fantasyTeam->team_name ?? 'N/A',
    //                 'user_name' => $participant->user->name ?? 'User',
    //                 'total_points' => $participant->total_points

    //             ];
    //         });

    //     return response()->json([
    //         'status' => true,
    //         'contest_id' => $contestId,
    //         'data' => $participants
    //     ]);
    // }

}