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

        foreach ($matches as $match) {

            $response = $api->getMatchSquad($match->api_match_id);
dd($response);

            if (!isset($response['data'])) {
                continue;
            }

            foreach ($response['data'] as $team) {

                foreach ($team['players'] as $playerData) {

                    $player = Player::updateOrCreate(
                        ['api_player_id' => $playerData['id']],
                        [
                            'name' => $playerData['name'],
                            'team_name' => $team['teamName'],
                            'role' => 'batsman', 
                            'credit' => 8.0
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
}