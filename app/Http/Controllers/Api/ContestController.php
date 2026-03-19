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

class ContestController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | Join Public Contest
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Create Private Contest
    |--------------------------------------------------------------------------
    */

    public function createPrivateContest(Request $request)
    {
        $request->validate([
            'cricket_match_id' => 'required|exists:cricket_matches,id',
            'entry_fee' => 'required|numeric|min:1',
            'total_slots' => 'required|integer|min:2'
        ]);

        $user = auth()->user();

        $platformFeePercent = 10;

        $totalCollection = $request->entry_fee * $request->total_slots;

        $platformFee = ($totalCollection * $platformFeePercent) / 100;

        $prizePool = $totalCollection - $platformFee;

        $privateCode = strtoupper(substr(md5(uniqid()), 0, 6));

        $contest = Contest::create([
            'cricket_match_id' => $request->cricket_match_id,
            'name' => 'Private Contest',
            'entry_fee' => $request->entry_fee,
            'total_slots' => $request->total_slots,
            'filled_slots' => 0,
            'prize_pool' => $prizePool,
            'platform_fee' => $platformFee,
            'status' => 'upcoming',
            'contest_type' => 'private',
            'private_code' => $privateCode
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Private contest created',
            'private_code' => $privateCode,
            'contest_id' => $contest->id
        ]);
    }


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

    public function leaderboard($contestId)
    {

        $contest = Contest::find($contestId);

        if (!$contest) {
            return response()->json([
                'status' => false,
                'message' => 'Contest not found'
            ], 404);
        }

        $participants = ContestParticipant::where('contest_id', $contestId)
            ->with(['user', 'fantasyTeam'])
            ->orderBy('rank')
            ->get()
            ->map(function ($participant) {

                return [

                    'rank' => $participant->rank,
                    'team_name' => $participant->fantasyTeam->team_name ?? 'N/A',
                    'user_name' => $participant->user->name ?? 'User',
                    'total_points' => $participant->total_points

                ];
            });

        return response()->json([
            'status' => true,
            'contest_id' => $contestId,
            'data' => $participants
        ]);
    }

}