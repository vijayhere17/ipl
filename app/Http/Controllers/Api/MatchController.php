<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CricketMatch;
use App\Services\CricketApiService;
use Illuminate\Support\Facades\Cache;

class MatchController extends Controller
{

    public function index(CricketApiService $service)
    {

        // Fetch matches from API every 60 seconds
        Cache::remember('cricket_matches_api', 60, function () use ($service) {

            $apiResponse = $service->getCurrentMatches();

            if(isset($apiResponse['status']) && $apiResponse['status'] === 'failure'){
                return [];
            }

            if (isset($apiResponse['data'])) {

                foreach ($apiResponse['data'] as $match) {

                   $status = 'upcoming';

if (isset($match['status'])) {

    $apiStatus = strtolower($match['status']);

    if (
        str_contains($apiStatus, 'won') ||
        str_contains($apiStatus, 'match over') ||
        str_contains($apiStatus, 'result')
    ) {
        $status = 'completed';
    }

    elseif (
        str_contains($apiStatus, 'need') ||
        str_contains($apiStatus, 'require') ||
        str_contains($apiStatus, 'trail') ||
        str_contains($apiStatus, 'live') ||
        str_contains($apiStatus, '/')
    ) {
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
            }

            return true;
        });


        $liveMatches = CricketMatch::where('status','live')
            ->orderBy('match_start_time','asc')
            ->get();

        $upcomingMatches = CricketMatch::where('status','upcoming')
            ->orderBy('match_start_time','asc')
            ->get();

        $completedMatches = CricketMatch::where('status','completed')
            ->orderBy('match_start_time','desc')
            ->limit(10)
            ->get();


        return response()->json([
            'status' => true,
            'data' => [
                'live_matches' => $liveMatches,
                'upcoming_matches' => $upcomingMatches,
                'completed_matches' => $completedMatches
            ]
        ]);
    }

    public function homeMatches()
{
    $matches = Cache::remember('home_matches', 30, function () {

        return CricketMatch::whereIn('status',['live','completed'])
            ->orderByRaw("FIELD(status,'live','completed')")
            ->orderBy('match_start_time','desc')
            ->limit(10)
            ->get();

    });

    $data = [];

    foreach($matches as $match){

        $data[] = [

            'match_id' => $match->api_match_id,

            'match_name' => $match->series_name,

            'team1' => $match->team_1,
            'team2' => $match->team_2,

            'team1_score' => $match->team1_score ?? 0,
            'team1_wicket' => $match->team1_wicket ?? 0,
            'team1_over' => $match->team1_over ?? '0.0',

            'team2_score' => $match->team2_score ?? 0,
            'team2_wicket' => $match->team2_wicket ?? 0,
            'team2_over' => $match->team2_over ?? '0.0',

            'status' => $match->status
        ];
    }

    return response()->json([
        'status' => true,
        'data' => $data
    ]);
}

    public function upcomingMatches()
{

    $matches = CricketMatch::where('status','upcoming')
        ->orderBy('match_start_time','asc')
        ->limit(10)
        ->get();

    $data = [];

    foreach($matches as $match){

        $data[] = [

            'match_id' => $match->api_match_id,

            'series_name' => $match->series_name,

            'team1' => $match->team_1,
            'team2' => $match->team_2,

            'start_time' => $match->match_start_time,

            'time' => $match->match_start_time->format('h:i A')

        ];
    }

    return response()->json([
        'status' => true,
        'data' => $data
    ]);
}

    public function matchInfo($id, CricketApiService $service)
    {
        $match = $service->getMatchInfo($id);

        return response()->json([
            'status' => true,
            'data' => $match['data'] ?? $match
        ]);
    }


    public function show($id)
    {
        $match = CricketMatch::where('api_match_id', $id)->first();

        if (!$match) {
            return response()->json([
                'status' => false,
                'message' => 'Match not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $match
        ]);
    }


    public function score($id, CricketApiService $service)
    {
        $score = $service->getScoreCard($id);

        return response()->json([
            'status' => true,
            'data' => $score['data'] ?? $score
        ]);
    }


    public function players($id, CricketApiService $service)
{
    $response = $service->getMatchSquad($id);

    if (!isset($response['data'])) {
        return response()->json([
            'status' => false,
            'message' => 'Squad not available'
        ]);
    }

    $teams = $response['data'];

    $team1 = $teams[0] ?? [];
    $team2 = $teams[1] ?? [];

    return response()->json([
        'status' => true,
        'data' => [

            'team1' => [
                'name' => $team1['teamName'] ?? '',
                'short' => $team1['shortName'] ?? '',
                'players' => $team1['players'] ?? []
            ],

            'team2' => [
                'name' => $team2['teamName'] ?? '',
                'short' => $team2['shortName'] ?? '',
                'players' => $team2['players'] ?? []
            ]

        ]
    ]);
}

    public function homeContests()
{

    $contests = \App\Models\Contest::with('match')
        ->where('status','upcoming')
        ->limit(5)
        ->get()
        ->map(function ($contest) {

            $match = $contest->match;

            return [

                'contest_id' => $contest->id,

                'contest_name' => $contest->name,

                'entry_fee' => $contest->entry_fee,

                'prize' => $contest->prize_pool,

                'match' => [

                    'match_id' => $match->api_match_id,

                    'series' => $match->series_name,

                    'team1' => $match->team_1,

                    'team2' => $match->team_2,

                    'match_time' => $match->match_start_time,

                    'time' => $match->match_start_time->format('h:i A')

                ]

            ];
        });

    return response()->json([
        'status' => true,
        'data' => $contests
    ]);
}

public function matchDetails($id, CricketApiService $service)
{
    $match = $service->getMatchInfo($id);

    if (!isset($match['data'])) {
        return response()->json([
            'status' => false,
            'message' => 'Match not found'
        ]);
    }

    $data = $match['data'];

    $dateTime = isset($data['dateTimeGMT'])
        ? \Carbon\Carbon::parse($data['dateTimeGMT'])->setTimezone('Asia/Kolkata')
        : null;

    $tossStatus = 'Yet to happen';

    if (!empty($data['tossWinner'])) {
        $tossStatus = $data['tossWinner'].' won the toss and chose to '.$data['tossChoice'];
    }

    // Fetch squad
    $squad = $service->getMatchSquad($id);

    $team1Names = '';
    $team2Names = '';
    $team1SquadName = '';
    $team2SquadName = '';

    if(isset($squad['data'])){

        $team1 = $squad['data'][0] ?? [];
        $team2 = $squad['data'][1] ?? [];

        $team1SquadName = $team1['teamName'] ?? '';
        $team2SquadName = $team2['teamName'] ?? '';

        $team1Players = array_column($team1['players'] ?? [], 'name');
        $team2Players = array_column($team2['players'] ?? [], 'name');

        $team1Names = implode(', ', $team1Players);
        $team2Names = implode(', ', $team2Players);
    }

    return response()->json([
        'status' => true,
        'data' => [

            // Basic
            'match_id' => $id,

            // Series Info
            'series_name' => $data['series'] ?? '',
            'match_name' => $data['name'] ?? '',
            'match_type' => $data['matchType'] ?? '',
            'match_number' => $data['matchNumber'] ?? '',

            // Date & Time
            'date' => $dateTime ? $dateTime->format('l, M d') : '',
            'time' => $dateTime ? $dateTime->format('h:i A') : '',
            'match_start_timestamp' => $dateTime ? $dateTime->timestamp : null,

            // Venue
            'venue' => $data['venue'] ?? '',
            'venue_city' => $data['city'] ?? '',

            // Teams
            'team1' => $data['teams'][0] ?? '',
            'team2' => $data['teams'][1] ?? '',

            'team1_short' => $data['teamInfo'][0]['shortname'] ?? '',
            'team2_short' => $data['teamInfo'][1]['shortname'] ?? '',

            'team1_logo' => $data['teamInfo'][0]['img'] ?? '',
            'team2_logo' => $data['teamInfo'][1]['img'] ?? '',

            // Toss
            'toss' => [
                'winner' => $data['tossWinner'] ?? null,
                'choice' => $data['tossChoice'] ?? null,
                'status' => $tossStatus
            ],

            // Match Status
            'status' => $data['status'] ?? '',

            // Pitch
            'pitch_type' => 'Batting',
            'pitch_support' => 'Pacers',

            // Venue Stats
            'venue_stats' => [
                'bat_first_win' => 40,
                'bat_second_win' => 60
            ],

            // Head to Head
            'head_to_head' => [
                'team1_wins' => 0,
                'team2_wins' => 0
            ],

            // Squads (for Info tab)
            'team1_squad_name' => $team1SquadName,
            'team2_squad_name' => $team2SquadName,

            'team1_squad' => $team1Names,
            'team2_squad' => $team2Names
        ]
    ]);
}

public function live(CricketApiService $service, $id)
{
    $info      = $service->getMatchInfo($id);
    $scorecard = $service->getScoreCard($id);
    $bbb       = $service->getBallByBall($id);
    $squad     = $service->getMatchSquad($id);

    $infoData  = $info['data'] ?? [];
    $data      = $scorecard['data'] ?? [];

    // =========================
    // ✅ BASIC INFO
    // =========================
    $status = $infoData['status'] ?? '';
    $teams  = $infoData['teamInfo'] ?? [];
    $scores = $infoData['score'] ?? [];

    $team1 = $teams[0] ?? [];
    $team2 = $teams[1] ?? [];

    $team1Score = $scores[0] ?? [];
    $team2Score = $scores[1] ?? [];

    // =========================
    // ✅ SCORE FORMAT
    // =========================
    $score = [
        'team1' => [
            'name' => $team1['shortname'] ?? '',
            'runs' => $team1Score['r'] ?? 0,
            'wickets' => $team1Score['w'] ?? 0,
            'overs' => $team1Score['o'] ?? '0'
        ],
        'team2' => [
            'name' => $team2['shortname'] ?? '',
            'runs' => $team2Score['r'] ?? 0,
            'wickets' => $team2Score['w'] ?? 0,
            'overs' => $team2Score['o'] ?? '0'
        ]
    ];

    // =========================
    // ✅ CURRENT INNINGS
    // =========================
    $currentInningsIndex = max(count($scores) - 1, 0);

    $batting = $data['scorecard'][$currentInningsIndex]['batting'] ?? [];

    // =========================
    // ✅ BATSMEN
    // =========================
    $batsmen = [];

    foreach ($batting as $bat) {
        $batsmen[] = [
            'name' => $bat['batsman']['name'] ?? '',
            'runs' => $bat['r'] ?? 0,
            'balls' => $bat['b'] ?? 0,
            'fours' => $bat['4s'] ?? 0,
            'sixes' => $bat['6s'] ?? 0,
            'sr' => $bat['sr'] ?? 0,
            'dismissal' => strtolower($bat['dismissal-text'] ?? '')
        ];
    }

    // 👉 last 2 batsmen (current)
    $currentBatsmen = array_slice($batsmen, -2);

    // =========================
    // ✅ BBB DATA FIX
    // =========================
    $bbbData = $bbb['data']['bbb'] ?? $bbb['data'] ?? [];

    // =========================
    // ✅ LAST OVER + BOWLER
    // =========================
    $lastOver = [];
    $currentBowler = null;

    if (!empty($bbbData)) {

        $maxOver = max(array_column($bbbData, 'over'));

        $lastBalls = array_filter($bbbData, function ($b) use ($maxOver) {
            return $b['over'] == $maxOver;
        });

        usort($lastBalls, fn($a, $b) => $a['ball'] <=> $b['ball']);

        foreach ($lastBalls as $ball) {

            if (!empty($ball['penalty'])) {
                $lastOver[] = strtoupper($ball['penalty']); // WD / NB
            } elseif (($ball['runs'] ?? 0) == 0) {
                $lastOver[] = '.';
            } else {
                $lastOver[] = $ball['runs'];
            }

            $currentBowler = $ball['bowler']['name'] ?? null;
        }
    }

   
    $team1Squad = array_map(fn($p) => $p['name'] ?? '', $squad['data'][0]['players'] ?? []);
    $team2Squad = array_map(fn($p) => $p['name'] ?? '', $squad['data'][1]['players'] ?? []);

    $toss = null;

    if (!empty($infoData['tossWinner'])) {
        $toss = ucfirst($infoData['tossWinner']) . ' chose to ' . $infoData['tossChoice'];
    }

    // =========================
    // ✅ FINAL RESPONSE
    // =========================
    return response()->json([
        'status' => true,
        'data' => [

            // 🔥 MATCH
            'match_name' => $infoData['name'] ?? '',
            'match_status' => $status,
            'venue' => $infoData['venue'] ?? '',

            // 🔥 TEAMS
            'teams' => [
                'team1' => $team1,
                'team2' => $team2
            ],

            // 🔥 SCORE
            'score' => $score,

            // 🔥 LAST OVER
            'last_over' => $lastOver,

            // 🔥 BATSMEN (LIVE)
            'batsmen' => $currentBatsmen,

            // 🔥 BOWLER
            'bowler' => $currentBowler,

            // 🔥 TOSS
            'toss' => $toss,

            // 🔥 SQUADS
            'team1_squad' => $team1Squad,
            'team2_squad' => $team2Squad
        ]
    ]);
}

public function scorecard($id, CricketApiService $service)
{
    $response = $service->getScoreCard($id);

    if (!isset($response['data'])) {
        return response()->json([
            'status' => false,
            'message' => 'Scorecard not available'
        ]);
    }

    $data = $response['data'];

    $innings = [];
    $batting = [];
    $bowling = [];
    $extras = [];
    $fallOfWickets = [];

    foreach ($data['score'] ?? [] as $s) {

        $innings[] = [
            'team' => $s['inning'] ?? '',
            'runs' => $s['r'] ?? 0,
            'wickets' => $s['w'] ?? 0,
            'overs' => $s['o'] ?? '0.0'
        ];
    }

    foreach ($data['scorecard'] ?? [] as $inningIndex => $inning) {

        $battingList = [];
        $bowlingList = [];
        $fow = [];

        $teamRuns = 0;
        $wicketCount = 0;

        foreach ($inning['batting'] ?? [] as $b) {

            $playerName = $b['batsman']['name'] ?? '';

            $battingList[] = [
                'name' => $playerName,
                'dismissal' => $b['dismissal-text'] ?? '',
                'runs' => $b['r'] ?? 0,
                'balls' => $b['b'] ?? 0,
                'fours' => $b['4s'] ?? 0,
                'sixes' => $b['6s'] ?? 0,
                'strike_rate' => $b['sr'] ?? ''
            ];

            $teamRuns += $b['r'] ?? 0;

            if (
                isset($b['dismissal-text']) &&
                $b['dismissal-text'] !== 'not out' &&
                $b['dismissal-text'] !== 'batting'
            ) {
                $wicketCount++;

                $fow[] = [
                    'player' => $playerName,
                    'score' => $teamRuns . '-' . $wicketCount,
                    'over' => ''
                ];
            }
        }

        foreach ($inning['bowling'] ?? [] as $b) {

            $bowlingList[] = [
                'name' => $b['bowler']['name'] ?? '',
                'overs' => $b['o'] ?? '',
                'maidens' => $b['m'] ?? 0,
                'runs' => $b['r'] ?? 0,
                'wickets' => $b['w'] ?? 0,
                'economy' => $b['eco'] ?? ''
            ];
        }

        $batting[] = $battingList;
        $bowling[] = $bowlingList;
        $fallOfWickets[] = $fow;

        $extras[] = [
            'runs' => $inning['extras']['r'] ?? 0,
            'byes' => $inning['extras']['b'] ?? 0
        ];
    }

    return response()->json([
        'status' => true,
        'data' => [
            'innings' => $innings,
            'batting' => $batting,
            'bowling' => $bowling,
            'extras' => $extras,
            'fall_of_wickets' => $fallOfWickets
        ]
    ]);
}

public function squads($id, CricketApiService $service)
{
    $response = $service->getMatchSquad($id);

    if (!isset($response['data'])) {
        return response()->json([
            'status' => false,
            'message' => 'Squads not available'
        ]);
    }

    $teams = $response['data'];

    $team1 = $teams[0] ?? [];
    $team2 = $teams[1] ?? [];

    $formatPlayers = function ($players) {
        return collect($players)->map(function ($player) {
            return [
                'id' => $player['id'] ?? null,
                'name' => $player['name'] ?? '',
                'role' => $player['role'] ?? '',
                'batting_style' => $player['battingStyle'] ?? '',
                'bowling_style' => $player['bowlingStyle'] ?? '',
                'country' => $player['country'] ?? '',
                'image' => $player['playerImg'] ?? null
            ];
        })->values();
    };

    return response()->json([
        'status' => true,
        'data' => [
            'team1' => [
                'name' => $team1['teamName'] ?? '',
                'short_name' => $team1['shortname'] ?? '',
                'logo' => $team1['img'] ?? '',
                'players' => $formatPlayers($team1['players'] ?? [])
            ],
            'team2' => [
                'name' => $team2['teamName'] ?? '',
                'short_name' => $team2['shortname'] ?? '',
                'logo' => $team2['img'] ?? '',
                'players' => $formatPlayers($team2['players'] ?? [])
            ]
        ]
    ]);
}
}