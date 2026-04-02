<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CricketApiService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.sportmonks.base_url');
        $this->apiKey  = config('services.sportmonks.key');
    }

    protected function client()
    {
        return Http::timeout(10)
            ->retry(2, 200)
            ->acceptJson();
    }

    /**
     * ✅ Fixtures (matches list)
     */
    public function getFixtures(): array
    {
        return Cache::remember('fixtures_list', 30, function () {

            $response = $this->client()->get("{$this->baseUrl}/fixtures", [
                'api_token' => $this->apiKey,
                'filter[starts_between]' => now()->subDay()->toDateString() . ',' . now()->addDays(5)->toDateString(),
                'include' => 'localteam,visitorteam,runs,league'
            ]);

            return $response->json() ?? [];
        });
    }

    /**
     * ✅ Match Info (teams + score)
     */
public function getMatchInfo($matchId): array
{
    return Cache::remember("match_info_$matchId", 60, function () use ($matchId) {

        $response = $this->client()->get("{$this->baseUrl}/fixtures/$matchId", [
            'api_token' => $this->apiKey,

            // ✅ ONLY ALLOWED INCLUDES
            'include' => 'league,venue,localteam,visitorteam,runs,lineup'
        ]);

        return $response->json() ?? [];
    });
}

public function getScorecardData($matchId): array
{
    return Cache::remember("scorecard_$matchId", 10, function () use ($matchId) {

        $response = $this->client()->get("{$this->baseUrl}/fixtures/$matchId", [
            'api_token' => $this->apiKey,
            'include'   => 'batting.batsman,bowling.bowler,runs'  // ✅ Fixed includes
        ]);

        return $response->json() ?? [];
    });
}

public function getRawScorecard($matchId): array
{
    $response = $this->client()->get("{$this->baseUrl}/fixtures/$matchId", [
        'api_token' => $this->apiKey,
        'include'   => 'batting.batsman,bowling.bowler,runs'
    ]);

    return $response->json() ?? [];
}
    /**
     * ✅ Scorecard (same as match info for Sportmonks)
     */
    public function getScoreCard($matchId): array
    {
        return $this->getMatchInfo($matchId);
    }

    /**
     * ✅ Ball by Ball (REAL LIVE DATA)
     */
   public function getBallByBall($matchId): array
{
    return Cache::remember("balls_$matchId", 3, function () use ($matchId) {

        $response = $this->client()->get("{$this->baseUrl}/fixtures/$matchId", [
            'api_token' => $this->apiKey,
            'include' => 'balls'
        ]);

        return $response->json() ?? [];
    });
}

    /**
     * ❌ Squad (not reliable in v2, so disabled)
     */
   public function getMatchSquad($matchId): array
{
    return Cache::remember("squad_$matchId", 60, function () use ($matchId) {

        $response = $this->client()->get("{$this->baseUrl}/fixtures/$matchId", [
            'api_token' => $this->apiKey,
            'include' => 'lineup,localteam,visitorteam'
        ]);

        $data = $response->json();

        // fallback if lineup missing
        if (empty($data['data']['lineup'])) {
            return [
                'team1' => [],
                'team2' => []
            ];
        }

        return $data;
    });
}
    /**
     * ✅ Live Matches
     */
    public function getLiveScores(): array
    {
        return Cache::remember('live_scores', 5, function () {

            $response = $this->client()->get("{$this->baseUrl}/livescores", [
                'api_token' => $this->apiKey,
                'include' => 'localteam,visitorteam,runs,lineup'
            ]);

            return $response->json() ?? [];
        });
    }

    /**
     * ✅ Auto detect active live match
     */
    public function getActiveMatchId(): ?int
    {
        $liveScores = $this->getLiveScores();

        if (empty($liveScores['data'])) {
            return null;
        }

        foreach ($liveScores['data'] as $match) {
            if (($match['live'] ?? false) == true) {
                return $match['id'];
            }
        }

        return null;
    }
}