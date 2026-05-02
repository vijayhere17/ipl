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
        $user = auth()->user();

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthenticated']);
        }

        $match = CricketMatch::find($request->cricket_match_id);

        if (!$match) {
            return response()->json(['status' => false, 'message' => 'Match not found']);
        }

        // Extract player IDs (handles both [53, 736] and [{"player_id": 53}] formats)
        $playerIds = collect($request->players)->map(function ($p) {
            return is_array($p) ? $p['player_id'] : $p;
        })->toArray();

        // Validate count before DB lookup
        if (count($playerIds) !== 11) {
            return response()->json([
                'status'  => false,
                'message' => 'Exactly 11 players required. Received: ' . count($playerIds),
            ]);
        }

        // Lookup by api_player_id + match_id
        $players = Player::whereIn('api_player_id', $playerIds)
                         ->where('cricket_match_id', $request->cricket_match_id)
                         ->get();

        if ($players->count() !== 11) {
            return response()->json([
                'status'  => false,
                'message' => 'Some players not found for this match. Found: ' . $players->count(),
                'debug'   => [
                    'sent_ids'   => $playerIds,
                    'found_ids'  => $players->pluck('api_player_id')->toArray(),
                    'missing'    => array_diff($playerIds, $players->pluck('api_player_id')->toArray()),
                ]
            ]);
        }

        // Validate captain and vice captain are in the team
        $apiIds = $players->pluck('api_player_id')->toArray();

        if (!in_array($request->captain, $apiIds)) {
            return response()->json(['status' => false, 'message' => 'Captain must be from selected players']);
        }

        if (!in_array($request->vice_captain, $apiIds)) {
            return response()->json(['status' => false, 'message' => 'Vice captain must be from selected players']);
        }

        if ($request->captain == $request->vice_captain) {
            return response()->json(['status' => false, 'message' => 'Captain and vice captain must be different']);
        }

        DB::beginTransaction();

        try {
            $team = FantasyTeam::create([
                'user_id'          => $user->id,
                'cricket_match_id' => $match->id,
                'team_name'        => $request->team_name ?? 'My Team',
                'total_points'     => 0,
            ]);

            foreach ($players as $player) {
                FantasyTeamPlayer::create([
                    'fantasy_team_id' => $team->id,
                    'player_id'       => $player->id, // DB auto-increment id
                    'is_captain'      => (string)$player->api_player_id === (string)$request->captain      ? 1 : 0,
                    'is_vice_captain' => (string)$player->api_player_id === (string)$request->vice_captain ? 1 : 0,
                ]);
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Team created successfully',
                'team_id' => $team->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    
    public function myTeams($match_id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthenticated']);
        }

        $match = CricketMatch::find($match_id);

        if (!$match) {
            return response()->json(['status' => false, 'message' => 'Match not found']);
        }

        $teams = FantasyTeam::with(['players.player'])
            ->where('user_id', $user->id)
            ->where('cricket_match_id', $match->id)
            ->get()
            ->map(function ($team) {

                $captain     = $team->players->firstWhere('is_captain', 1);
                $viceCaptain = $team->players->firstWhere('is_vice_captain', 1);

                // Count players by role
                $roleCounts = $team->players->groupBy(function ($fp) {
                    return $fp->player->role ?? 'bat';
                })->map->count();

                return [
                    'team_id'      => $team->id,
                    'team_name'    => $team->team_name,
                    'total_points' => $team->total_points,
                    'player_count' => $team->players->count(),
                    'captain'      => [
                        'name'  => $captain?->player?->name ?? null,
                        'image' => $captain?->player?->image ?? null,
                        'role'  => $captain?->player?->role  ?? null,
                    ],
                    'vice_captain' => [
                        'name'  => $viceCaptain?->player?->name ?? null,
                        'image' => $viceCaptain?->player?->image ?? null,
                        'role'  => $viceCaptain?->player?->role  ?? null,
                    ],
                    'role_counts'  => [
                        'wk'   => $roleCounts['wk']   ?? 0,
                        'bat'  => $roleCounts['bat']  ?? 0,
                        'ar'   => $roleCounts['ar']   ?? 0,
                        'bowl' => $roleCounts['bowl'] ?? 0,
                    ],
                ];
            });

        return response()->json([
            'status' => true,
            'count'  => $teams->count(),
            'data'   => $teams,
        ]);
    }

   
   public function teamPreview($team_id)
{
    $team = \App\Models\FantasyTeam::with(['players.player'])->find($team_id);

    if (!$team) {
        return response()->json([
            'status' => false,
            'message' => 'Team not found'
        ]);
    }

    $service = app(\App\Services\CricketApiService::class);

    app(\App\Http\Controllers\Api\ContestController::class)
        ->calculatePlayerPoints($team->match_id, $service);

    $team->load(['players.player']);

    $players = $team->players->map(function ($fp) {
        $p = $fp->player;

        if (!$p) {
            return null;
        }

        $basePoints = $p->points ?? 0;

        if ($fp->is_captain) {
            $finalPoints = $basePoints * 2;
        } elseif ($fp->is_vice_captain) {
            $finalPoints = $basePoints * 1.5;
        } else {
            $finalPoints = $basePoints;
        }

        return [
            'player_id'       => $p->id,
            'api_player_id'   => $p->api_player_id ?? null,
            'name'            => $p->name ?? 'Unknown',
            'role'            => strtolower($p->role ?? 'bat'),
            'team_code'       => $p->team_code ?? '',
            'image'           => $p->image ?? url('/default-player.png'),
            'credit'          => $p->credit ?? 8,
            'points'          => $basePoints,
            'final_points'    => $finalPoints,
            'is_captain'      => (bool) $fp->is_captain,
            'is_vice_captain' => (bool) $fp->is_vice_captain,
        ];
    })->filter()->values();

    $byRole = [
        'wk'   => $players->where('role', 'wk')->values(),
        'bat'  => $players->where('role', 'bat')->values(),
        'ar'   => $players->where('role', 'ar')->values(),
        'bowl' => $players->where('role', 'bowl')->values(),
    ];

    $totalPoints = $players->sum('final_points');

    return response()->json([
        'status' => true,
        'data' => [
            'team_id'      => $team->id,
            'team_name'    => $team->team_name,
            'total_points' => $totalPoints,
            'players'      => $players,
            'by_role'      => $byRole,
        ]
    ]);
}

    public function updateTeam(Request $request, $team_id)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json(['status' => false, 'message' => 'Unauthenticated']);
    }

    // Find team — must belong to this user
    $team = FantasyTeam::where('id', $team_id)
        ->where('user_id', $user->id)
        ->first();

    if (!$team) {
        return response()->json(['status' => false, 'message' => 'Team not found']);
    }

    // Check match hasn't started yet
    $match = CricketMatch::find($team->cricket_match_id);

    if ($match && now() >= $match->match_start_time) {
        return response()->json([
            'status'  => false,
            'message' => 'Cannot update team after match has started'
        ]);
    }

    // Extract player IDs
    $playerIds = collect($request->players)->map(function ($p) {
        return is_array($p) ? $p['player_id'] : $p;
    })->toArray();

    if (count($playerIds) !== 11) {
        return response()->json([
            'status'  => false,
            'message' => 'Exactly 11 players required. Got: ' . count($playerIds),
        ]);
    }

    // Lookup players by api_player_id
    $players = Player::whereIn('api_player_id', $playerIds)
        ->where('cricket_match_id', $team->cricket_match_id)
        ->get();

    if ($players->count() !== 11) {
        return response()->json([
            'status'  => false,
            'message' => 'Some players not found. Found: ' . $players->count(),
            'debug'   => [
                'sent'    => $playerIds,
                'found'   => $players->pluck('api_player_id')->toArray(),
                'missing' => array_diff($playerIds, $players->pluck('api_player_id')->toArray()),
            ]
        ]);
    }

    // Validate captain and vice captain
    $apiIds = $players->pluck('api_player_id')->toArray();

    if ($request->captain && !in_array((string)$request->captain, array_map('strval', $apiIds))) {
        return response()->json(['status' => false, 'message' => 'Captain must be from selected players']);
    }

    if ($request->vice_captain && !in_array((string)$request->vice_captain, array_map('strval', $apiIds))) {
        return response()->json(['status' => false, 'message' => 'Vice captain must be from selected players']);
    }

    if ($request->captain == $request->vice_captain) {
        return response()->json(['status' => false, 'message' => 'Captain and vice captain must be different']);
    }

    DB::beginTransaction();

    try {
        // Update team name if provided
        if ($request->team_name) {
            $team->update(['team_name' => $request->team_name]);
        }

        // Delete old players
        FantasyTeamPlayer::where('fantasy_team_id', $team->id)->delete();

        // Insert new players
        foreach ($players as $player) {
            FantasyTeamPlayer::create([
                'fantasy_team_id' => $team->id,
                'player_id'       => $player->id,
                'is_captain'      => (string)$player->api_player_id === (string)$request->captain      ? 1 : 0,
                'is_vice_captain' => (string)$player->api_player_id === (string)$request->vice_captain ? 1 : 0,
            ]);
        }

        DB::commit();

        return response()->json([
            'status'  => true,
            'message' => 'Team updated successfully',
            'data'    => [
                'team_id'   => $team->id,
                'team_name' => $team->team_name,
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status'  => false,
            'message' => 'Something went wrong',
            'error'   => $e->getMessage(),
        ], 500);
    }
}
}