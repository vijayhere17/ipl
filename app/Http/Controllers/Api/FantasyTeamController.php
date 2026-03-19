<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\Player;

class FantasyTeamController extends Controller
{
    public function createTeam(Request $request)
    {

        $request->validate([
            'cricket_match_id' => 'required|exists:cricket_matches,id',
            'team_name' => 'required|string|max:100',
            'players' => 'required|array|min:11|max:11',
        ]);

        $user = auth()->user();

        DB::beginTransaction();

        try {

            // Create team
            $team = FantasyTeam::create([
                'user_id' => $user->id,
                'cricket_match_id' => $request->cricket_match_id,
                'team_name' => $request->team_name,
                'total_points' => 0
            ]);

            foreach ($request->players as $player) {

                FantasyTeamPlayer::create([
                    'fantasy_team_id' => $team->id,
                    'player_id' => $player['player_id'],
                    'is_captain' => $player['is_captain'] ?? 0,
                    'is_vice_captain' => $player['is_vice_captain'] ?? 0,
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

    $teams = FantasyTeam::where('user_id', $user->id)
        ->where('cricket_match_id', $match_id)
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
                    ? Player::find($captain->player_id)->name
                    : null,

                'vice_captain' => $viceCaptain
                    ? Player::find($viceCaptain->player_id)->name
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

    $team = FantasyTeam::find($team_id);

    if(!$team){
        return response()->json([
            'status'=>false,
            'message'=>'Team not found'
        ]);
    }

    $players = FantasyTeamPlayer::where('fantasy_team_id',$team_id)
        ->join('players','players.id','=','fantasy_team_players.player_id')
        ->select(
            'players.id',
            'players.name',
            'players.team_name',
            'players.role',
            'players.credit',
            'fantasy_team_players.is_captain',
            'fantasy_team_players.is_vice_captain'
        )
        ->get();

    return response()->json([
        'status'=>true,
        'team'=>[
            'team_id'=>$team->id,
            'team_name'=>$team->team_name
        ],
        'players'=>$players
    ]);
}
    
}