<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CricketApiService;
use App\Models\CricketMatch;
use App\Models\Player;
use App\Models\MatchPlayer;

class SyncMatchSquads extends Command
{
    protected $signature = 'sync:squads';
    protected $description = 'Sync squads for matches';

    public function handle(CricketApiService $api)
    {
        $matches = CricketMatch::all();

        // Team map for codes
        $teamMap = [
            'Mumbai Indians' => 'MI',
            'Chennai Super Kings' => 'CSK',
            'Royal Challengers Bengaluru' => 'RCB',
            'Sunrisers Hyderabad' => 'SRH',
            'Kolkata Knight Riders' => 'KKR',
            'Delhi Capitals' => 'DC',
            'Punjab Kings' => 'PBKS',
            'Rajasthan Royals' => 'RR',
            'Lucknow Super Giants' => 'LSG',
            'Gujarat Titans' => 'GT',
        ];

        foreach ($matches as $match) {

            $response = $api->getMatchSquad($match->api_match_id);

            if (!isset($response['data']['lineup'])) {
                continue;
            }

            $lineup = $response['data']['lineup'];

            // Group by team_id
            $teams = [];
            foreach ($lineup as $player) {
                $teamId = $player['lineup']['team_id'] ?? null;
                if ($teamId) {
                    $teams[$teamId][] = $player;
                }
            }

            foreach ($teams as $teamId => $players) {

                // Get team name from local or visitor
                $teamName = '';
                if ($response['data']['localteam_id'] == $teamId) {
                    $teamName = $response['data']['localteam']['name'] ?? '';
                } elseif ($response['data']['visitorteam_id'] == $teamId) {
                    $teamName = $response['data']['visitorteam']['name'] ?? '';
                }

                $teamCode = $teamMap[$teamName] ?? $teamName;

                foreach ($players as $playerData) {

                    $player = Player::updateOrCreate(
                        ['api_player_id' => $playerData['id']],
                        [
                            'name' => $playerData['fullname'],
                            'team_name' => $teamCode,
                            'role' => $this->mapRole($playerData['position']['name'] ?? 'BAT'), 
                            'credit' => 8.0 // or from API if available
                        ]
                    );

                    MatchPlayer::updateOrCreate(
                        [
                            'cricket_match_id' => $match->id,
                            'player_id' => $player->id
                        ],
                        [
                            'credit' => 8.0,
                            'is_playing' => false
                        ]
                    );
                }
            }
        }

        $this->info('Squads synced successfully!');
    }

    private function mapRole($role)
    {
        $role = strtolower($role);

        if (str_contains($role, 'wk') || str_contains($role, 'wicket')) return 'WK';

        if (str_contains($role, 'all')) return 'ALL';

        if (str_contains($role, 'bowl')) return 'BOWL';

        if (str_contains($role, 'bat')) return 'BAT';

        return 'BAT';
    }
}