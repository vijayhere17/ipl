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
    return Cache::remember("match_$matchId", 0.5, function () use ($matchId) {  // Reduced cache to 30 seconds for live data

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
            'include'   => 'batting.batsman,batting.wicket,bowling.bowler,runs,localteam,visitorteam',
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
     * ✅ Ball by Ball (REAL LIVE DATA) - Processed for live summary
     */
  public function getBallByBall($matchId): array
{
    return Cache::remember("balls_$matchId", 0.5, function () use ($matchId) {

        $response = $this->client()->get("{$this->baseUrl}/fixtures/$matchId", [
            'api_token' => $this->apiKey,
            'include' => 'balls'
        ]);

        $data = $response->json();
        $balls = $data['data']['balls'] ?? [];

        if (empty($balls)) {
            return [];
        }

        $balls = collect($balls);

        // =========================
        // CURRENT INNINGS
        // =========================
        $innings = $balls->groupBy('scoreboard');

        $current_innings_key = $innings->map(function ($inningBalls) {
            return $inningBalls->max('updated_at');
        })->sortDesc()->keys()->first();

        $current_balls = $innings[$current_innings_key];

        // =========================
        // TOTAL SCORE
        // =========================
        $total_runs = $current_balls->sum(fn($b) => $b['score']['runs'] ?? 0);
        $wickets = $current_balls->where('score.is_wicket', true)->count();

        $max_ball = $current_balls->max('ball');
        $overs = floor($max_ball) . '.' . intval(($max_ball - floor($max_ball)) * 10);

        $team_name = $current_balls->first()['team']['name'] ?? 'Unknown';

        // =========================
        // CURRENT BATSMEN (FIXED 🔥)
        // =========================
        $latest_ball = $current_balls->sortByDesc('updated_at')->first();

        $batsman_ids = [
            $latest_ball['batsman_one_on_creeze_id'] ?? null,
            $latest_ball['batsman_two_on_creeze_id'] ?? null
        ];

        $current_batsmen = [];

        foreach ($batsman_ids as $id) {

            if (!$id) continue;

            $player_balls = $current_balls->where('batsman_id', $id);

            $runs = 0;
            $balls_faced = 0;
            $fours = 0;
            $sixes = 0;

            foreach ($player_balls as $ball) {

                $ball_runs = $ball['score']['runs'] ?? 0;
                $is_ball = $ball['score']['ball'] ?? false;

                if ($is_ball) {
                    $balls_faced++;
                }

                $runs += $ball_runs;

                if ($ball_runs == 4) $fours++;
                if ($ball_runs == 6) $sixes++;
            }

            $strike_rate = $balls_faced > 0 ? round(($runs / $balls_faced) * 100, 2) : 0;

            $name = $player_balls->first()['batsman']['fullname'] ?? 'Unknown';

            $current_batsmen[] = [
                'name' => $name,
                'runs' => $runs,
                'balls' => $balls_faced,
                'fours' => $fours,
                'sixes' => $sixes,
                'strike_rate' => $strike_rate
            ];
        }

        // =========================
        // CURRENT BOWLER
        // =========================
        $bowler_id = $latest_ball['bowler_id'] ?? null;

        $bowler_balls = $current_balls->where('bowler_id', $bowler_id);

        $bowler_runs = $bowler_balls->sum(fn($b) => $b['score']['runs'] ?? 0);
        $bowler_wickets = $bowler_balls->where('score.is_wicket', true)->count();
        $bowler_ball_count = $bowler_balls->where('score.ball', true)->count();

        $bowler_overs = floor($bowler_ball_count / 6) . '.' . ($bowler_ball_count % 6);
        $bowler_name = $bowler_balls->first()['bowler']['fullname'] ?? 'Unknown';

        // =========================
        // LAST OVER
        // =========================
        $recent_balls = $current_balls
    ->sortByDesc('updated_at')
    ->take(6)
    ->map(function ($ball) {

        if (($ball['score']['is_wicket'] ?? false) === true) {
            return 'W';
        }

        return $ball['score']['runs'] ?? 0;
    })
    ->values()
    ->toArray();

        return [
            'score' => $total_runs . '/' . $wickets,
            'overs' => $overs,
            'batting_team' => $team_name,
            'current_batsmen' => $current_batsmen,
            'current_bowler' => [
                'name' => $bowler_name,
                'overs' => $bowler_overs,
                'runs' => $bowler_runs,
                'wickets' => $bowler_wickets
            ],
            'recent_balls' => $recent_balls
        ];
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

        return $response->json() ?? [];
    });
}  

/**
 * ✅ Get processed scorecard (uses MatchController scorecard logic)
 * Returns clean batting/bowling arrays matching our scorecard API response
 */
public function getProcessedScorecard($matchId): array
{
    return Cache::remember("processed_scorecard_$matchId", 30, function () use ($matchId) {

        $response = $this->client()->get("{$this->baseUrl}/fixtures/$matchId", [
            'api_token' => $this->apiKey,
            'include'   => 'batting.batsman,batting.wicket,bowling.bowler,runs',
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data    = $response->json()['data'] ?? [];
        $batting = $data['batting'] ?? [];
        $bowling = $data['bowling'] ?? [];

        // Group by scoreboard (innings)
        $battingByInnings = collect($batting)->groupBy('scoreboard')->values()->toArray();
        $bowlingByInnings = collect($bowling)->groupBy('scoreboard')->values()->toArray();

        $formattedBatting = [];
        $formattedBowling = [];

        // Format batting innings
        foreach ($battingByInnings as $inning) {
            $inningData = [];
            foreach ($inning as $b) {
                $runs   = $b['score']['runs']   ?? 0;
                $balls  = $b['score']['balls']  ?? 0;
                $fours  = $b['score']['fours']  ?? 0;
                $sixes  = $b['score']['sixes']  ?? 0;
                $sr     = $balls > 0 ? round(($runs / $balls) * 100, 2) : 0;

                $inningData[] = [
                    'name'         => $b['batsman']['fullname'] ?? $b['batsman']['name'] ?? 'Unknown',
                    'runs'         => $runs,
                    'balls'        => $balls,
                    'fours'        => $fours,
                    'sixes'        => $sixes,
                    'strike_rate'  => $sr,
                    'dismissal'    => isset($b['score']['how_out']) ? 'out' : 'not out',
                ];
            }
            $formattedBatting[] = $inningData;
        }

        // Format bowling innings
        foreach ($bowlingByInnings as $inning) {
            $inningData = [];
            foreach ($inning as $b) {
                $overs   = $b['overs']   ?? 0;
                $runs    = $b['runs']    ?? 0;
                $economy = $overs > 0 ? round($runs / $overs, 2) : 0;

                $inningData[] = [
                    'name'    => $b['bowler']['fullname'] ?? $b['bowler']['name'] ?? 'Unknown',
                    'overs'   => $overs,
                    'maidens' => $b['medians'] ?? 0,
                    'runs'    => $runs,
                    'wickets' => $b['wickets'] ?? 0,
                    'economy' => $economy,
                ];
            }
            $formattedBowling[] = $inningData;
        }

        return [
            'data' => [
                'batting' => $formattedBatting,
                'bowling' => $formattedBowling,
            ]
        ];
    });
}

public function getTeamSquad($teamId, $seasonId = null): array
{
    $cacheKey = "team_squad_{$teamId}_{$seasonId}";

    return Cache::remember($cacheKey, 3600, function () use ($teamId, $seasonId) {

        if ($seasonId) {
            $response = $this->client()->get("{$this->baseUrl}/teams/{$teamId}/squad/{$seasonId}", [
                'api_token' => $this->apiKey,
            ]);
        } else {
            $response = $this->client()->get("{$this->baseUrl}/teams/{$teamId}", [
                'api_token' => $this->apiKey,
                'include' => 'squad'
            ]);
        }

        return $response->json() ?? [];
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