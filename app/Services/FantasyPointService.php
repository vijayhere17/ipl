<?php

namespace App\Services;

use App\Models\CricketMatch;
use App\Models\FantasyTeam;
use App\Models\ContestParticipant;
use App\Models\FantasyTeamPlayer;
use App\Models\Player;

class FantasyPointService
{
    // 🎯 MAIN FUNCTION
    public function updateMatchPoints($match_id)
    {
        $apiService = app(\App\Services\CricketApiService::class);
        $response = $apiService->getMatchScorecard($match_id);

        if (empty($response['data'])) return;

        // 🔥 LOOP PLAYERS FROM API
        foreach ($response['data'] as $playerStats) {

            $apiPlayerId = $playerStats['player_id'] ?? null;
            if (!$apiPlayerId) continue;

            // 🔍 FIND PLAYER
            $player = Player::where('api_player_id', $apiPlayerId)
                ->where('cricket_match_id', $match_id)
                ->first();

            if (!$player) continue;

            // 🧮 CALCULATE POINTS
            $points = $this->calculatePoints($playerStats);

            // 💾 SAVE
            $player->points = $points;
            $player->save();
        }

        // 🔥 UPDATE ALL TEAMS
        $this->updateTeamPoints($match_id);
    }

    // 🎯 POINT LOGIC
    private function calculatePoints($s)
    {
        $points = 0;

        $points += ($s['runs'] ?? 0);
        $points += ($s['fours'] ?? 0) * 1;
        $points += ($s['sixes'] ?? 0) * 2;
        $points += ($s['wickets'] ?? 0) * 25;
        $points += ($s['catches'] ?? 0) * 8;
        $points += ($s['runouts'] ?? 0) * 10;

        return $points;
    }

    // 🎯 TEAM POINT UPDATE
    private function updateTeamPoints($match_id)
    {
        $teams = FantasyTeam::where('match_id', $match_id)
            ->with('players')
            ->get();

        foreach ($teams as $team) {

            $total = 0;

            foreach ($team->players as $tp) {

                $player = Player::find($tp->player_id);
                if (!$player) continue;

                $pts = $player->points ?? 0;

                // 👑 CAPTAIN
                if ($tp->is_captain) {
                    $pts *= 2;
                }

                // ⭐ VICE CAPTAIN
                if ($tp->is_vice_captain) {
                    $pts *= 1.5;
                }

                $total += $pts;
            }

            // 💾 UPDATE PARTICIPANT
            ContestParticipant::where('fantasy_team_id', $team->id)
                ->update(['total_points' => $total]);
        }
    }
}