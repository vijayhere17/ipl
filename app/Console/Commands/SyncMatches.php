<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CricketApiService;
use App\Models\CricketMatch;

class SyncMatches extends Command
{
    protected $signature = 'sync:matches';
    protected $description = 'Sync matches from CricAPI';

  public function handle(CricketApiService $api)
{
    $response = $api->getCurrentMatches();

    if (!isset($response['status']) || $response['status'] !== 'success') {
        $this->error('API failed: '.($response['reason'] ?? 'Unknown error'));
        return;
    }

    if (!isset($response['data'])) {
        $this->error('No matches found');
        return;
    }

    foreach ($response['data'] as $match) {

        CricketMatch::updateOrCreate(
            ['api_match_id' => $match['id']],
            [
                'series_name' => $match['series'] ?? null,
                'team_1' => $match['teamInfo'][0]['name'] ?? '',
                'team_2' => $match['teamInfo'][1]['name'] ?? '',
                'match_start_time' => $match['dateTimeGMT'] ?? now(),
                'status' => $this->mapStatus($match['status']),
            ]
        );
    }

    $this->info('Matches synced successfully!');
}

    private function mapStatus($status)
    {
        $status = strtolower($status);

        if (str_contains($status, 'live')) return 'live';
        if (str_contains($status, 'completed')) return 'completed';

        return 'upcoming';
    }
}