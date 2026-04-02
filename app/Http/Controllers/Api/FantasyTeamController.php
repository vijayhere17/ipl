<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\Player;
use App\Models\CricketMatch;

class FantasyTeamController extends Controller
{
   public function createTeam(Request $request)
{
    $request->validate([
        'cricket_match_id' => 'required|exists:cricket_matches,api_match_id',
        'team_name' => 'required|string|max:100',
        'players' => 'required|array|min:11|max:11',
        'captain' => 'required',
        'vice_captain' => 'required|different:captain',
    ]);

    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'User not authenticated'
        ]);
    }

    // ✅ Get match
    $match = CricketMatch::where('api_match_id', $request->cricket_match_id)->first();

    if (!$match) {
        return response()->json([
            'status' => false,
            'message' => 'Match not found'
        ]);
    }

    // ✅ Extract player IDs
    $playerIds = collect($request->players)->pluck('player_id')->toArray();

    // 🔴 1. VALIDATE PLAYERS BELONG TO MATCH
    $validPlayers = Player::whereIn('id', $playerIds)
        ->where('cricket_match_id', $match->id)
        ->count();

    if ($validPlayers !== count($playerIds)) {
        return response()->json([
            'status' => false,
            'message' => 'Invalid players selected for this match'
        ]);
    }

    // 🔴 2. DREAM11 ROLE VALIDATION
    $players = Player::whereIn('id', $playerIds)->get();

    $wk = $players->where('role', 'WK')->count();
    $bat = $players->where('role', 'BAT')->count();
    $all = $players->where('role', 'ALL')->count();
    $bowl = $players->where('role', 'BOWL')->count();

    if ($wk < 1 || $wk > 4) return response()->json(['status'=>false,'message'=>'WK must be 1-4']);
    if ($bat < 3 || $bat > 6) return response()->json(['status'=>false,'message'=>'BAT must be 3-6']);
    if ($all < 1 || $all > 4) return response()->json(['status'=>false,'message'=>'ALL must be 1-4']);
    if ($bowl < 3 || $bowl > 6) return response()->json(['status'=>false,'message'=>'BOWL must be 3-6']);

    // 🔴 3. CAPTAIN / VC VALIDATION
    if (!in_array($request->captain, $playerIds) ||
        !in_array($request->vice_captain, $playerIds)) {

        return response()->json([
            'status' => false,
            'message' => 'Captain or Vice Captain not in selected players'
        ]);
    }

    // 🔴 4. MAX TEAM LIMIT (OPTIONAL)
    $teamCount = FantasyTeam::where('user_id', $user->id)
        ->where('cricket_match_id', $match->id)
        ->count();

    if ($teamCount >= 20) {
        return response()->json([
            'status' => false,
            'message' => 'Maximum 20 teams allowed per match'
        ]);
    }

    DB::beginTransaction();

    try {

        // ✅ CREATE TEAM
        $team = FantasyTeam::create([
            'user_id' => $user->id,
            'cricket_match_id' => $match->id,
            'team_name' => $request->team_name,
            'total_points' => 0
        ]);

        foreach ($playerIds as $pid) {

            FantasyTeamPlayer::create([
                'fantasy_team_id' => $team->id,
                'player_id' => $pid,
                'is_captain' => $pid == $request->captain ? 1 : 0,
                'is_vice_captain' => $pid == $request->vice_captain ? 1 : 0,
            ]);
        }

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Team created successfully',
            'team_id' => $team->id
        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([
            'status' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

   public function myTeams($match_id)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'User not authenticated'
        ]);
    }

    // ✅ Convert UUID → DB ID
    $match = CricketMatch::where('api_match_id', $match_id)->first();

    if (!$match) {
        return response()->json([
            'status' => false,
            'message' => 'Match not found'
        ]);
    }

    $teams = FantasyTeam::where('user_id', $user->id)
        ->where('cricket_match_id', $match->id)
        ->get()
        ->map(function ($team) {

            $captain = FantasyTeamPlayer::where('fantasy_team_id', $team->id)
                ->where('is_captain', 1)
                ->first();

            $viceCaptain = FantasyTeamPlayer::where('fantasy_team_id', $team->id)
                ->where('is_vice_captain', 1)
                ->first();

            return [
                'team_id' => $team->id,
                'team_name' => $team->team_name,

                'captain' => $captain
                    ? optional(Player::find($captain->player_id))->name
                    : null,

                'vice_captain' => $viceCaptain
                    ? optional(Player::find($viceCaptain->player_id))->name
                    : null,
            ];
        });

    return response()->json([
        'status' => true,
        'data' => $teams
    ]);
}

   public function teamPreview($team_id)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'User not authenticated'
        ]);
    }

    $team = FantasyTeam::where('id', $team_id)
        ->where('user_id', $user->id)
        ->first();

    if (!$team) {
        return response()->json([
            'status' => false,
            'message' => 'Team not found'
        ]);
    }

    $players = FantasyTeamPlayer::where('fantasy_team_id', $team->id)
        ->get()
        ->map(function ($player) {

            $playerData = Player::find($player->player_id);

            return [
    'player_id' => $player->player_id,
    'name' => $playerData->name ?? '',
    'role' => $playerData->role ?? 'BAT',
    'image' => $playerData->image ?? url('/default-player.png'),

   
    'points' => $playerData->points ?? 0,

    'is_captain' => $player->is_captain,
    'is_vice_captain' => $player->is_vice_captain,
];
        });

    return response()->json([
        'status' => true,
        'data' => [
            'team_id' => $team->id,
            'team_name' => $team->team_name,
            'players' => $players
        ]
    ]);
}
    
}