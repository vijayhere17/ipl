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
        $this->baseUrl = config('services.cricapi.base_url');
        $this->apiKey  = config('services.cricapi.key');
    }

    protected function client()
    {
        return Http::timeout(10)
            ->retry(2, 200)
            ->acceptJson();
    }

    /**
     * Get current matches (cached 2 minutes)
     */
    public function getCurrentMatches(): array
    {
        return Cache::remember('cricapi_current_matches', 120, function () {

            $response = $this->client()->get("{$this->baseUrl}/currentMatches", [
                'apikey' => $this->apiKey,
                'offset' => 0
            ]);

            return $response->json() ?? [];

        });
    }

    /**
     * Match info (cached 10 minutes)
     */
    public function getMatchInfo($matchId): array
    {
        return Cache::remember("cricapi_match_info_$matchId", 600, function () use ($matchId) {

            $response = $this->client()->get("{$this->baseUrl}/match_info", [
                'apikey' => $this->apiKey,
                'id'     => $matchId
            ]);

            return $response->json() ?? [];

        });
    }

    /**
     * Live scorecard (cached 20 seconds)
     */
    public function getScoreCard($matchId): array
{
    return Cache::remember("cricapi_score_$matchId", 20, function () use ($matchId) {

        $response = $this->client()->get("{$this->baseUrl}/match_scorecard", [
            'apikey' => $this->apiKey,
            'id'     => $matchId
        ])->json();

        // 🔥 IMPORTANT FIX
        if (empty($response) || empty($response['data'])) {
            return []; // ❌ DO NOT CACHE EMPTY RESPONSE
        }

        return $response;
    });
}

    /**
     * Match squad (cached 1 hour)
     */
  public function getMatchSquad($matchId): array
{
    return Cache::remember("cricapi_match_squad_$matchId", 3600, function () use ($matchId) {

        $response = $this->client()->get("{$this->baseUrl}/match_squad", [
            'apikey' => $this->apiKey,
            'id' => $matchId
        ]);

        return $response->json() ?? [];
    });
}

    /**
     * Upcoming matches (cached 10 minutes)
     */
    public function getUpcomingMatches(): array
    {
        return Cache::remember('cricapi_upcoming_matches', 600, function () {

            $response = $this->client()->get("{$this->baseUrl}/matches", [
                'apikey' => $this->apiKey,
                'offset' => 0
            ]);

            return $response->json() ?? [];

        });
    }

    public function getBallByBall($matchId): array
{
    return Cache::remember("cricapi_bbb_$matchId", 10, function () use ($matchId) {

        $response = $this->client()->get("{$this->baseUrl}/match_bbb", [
            'apikey' => $this->apiKey,
            'id' => $matchId
        ])->json();

        // 🔥 IMPORTANT FIX
        if (empty($response) || empty($response['data'])) {
            return [];
        }

        return $response;
    });
}

public function getLiveScores(): array
{
    return Cache::remember('cricapi_live_scores', 10, function () {

        $response = $this->client()->get("{$this->baseUrl}/cricScore", [
            'apikey' => $this->apiKey
        ])->json();

        return $response ?? [];
    });
}

public function getActiveMatchId(): ?string
{
    $liveScores = $this->getLiveScores();

    if (empty($liveScores['data'])) {
        return null;
    }

    foreach ($liveScores['data'] as $match) {

        if (
            ($match['matchStarted'] ?? false) === true &&
            ($match['matchEnded'] ?? false) === false
        ) {
            return $match['id'];
        }
    }

    return null;
}


}