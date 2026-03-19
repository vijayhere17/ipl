<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CricketApiService;
use App\Models\CricketMatch;

class UpdateMatches extends Command
{
    protected $signature = 'matches:update';

    protected $description = 'Update cricket matches from API';

    public function handle(CricketApiService $service)
    {

        $this->info("Fetching matches from API...");

        $response = $service->getCurrentMatches();

        if (!isset($response['data'])) {
            $this->error("No matches found.");
            return;
        }

        foreach ($response['data'] as $match) {

            $status = 'upcoming';

            if (isset($match['status'])) {

                $apiStatus = strtolower($match['status']);

                if (str_contains($apiStatus, 'won') || str_contains($apiStatus, 'match over')) {
                    $status = 'completed';
                } 
                elseif (str_contains($apiStatus, '/')) {
                    $status = 'live';
                }
            }

            CricketMatch::updateOrCreate(
                ['api_match_id' => $match['id']],
                [
                    'series_name' => $match['name'] ?? '',
                    'team_1' => $match['teams'][0] ?? '',
                    'team_2' => $match['teams'][1] ?? '',
                    'match_start_time' => $match['dateTimeGMT'] ?? now(),
                    'status' => $status
                ]
            );
        }

        $this->info("Matches updated successfully!");
    }
}