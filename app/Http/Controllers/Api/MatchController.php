<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CricketMatch;
use App\Services\CricketApiService;
use Illuminate\Support\Facades\Cache;
use App\Models\Player;
use Carbon\Carbon;

class MatchController extends Controller
{

public function index(CricketApiService $service)
{
    $apiResponse = $service->getFixtures();

    // ✅ TEAM MAP (IMPORTANT)
    $teamMap = [
        'Mumbai Indians' => ['code' => 'MI', 'logo' => 'https://cdn.sportmonks.com/images/cricket/teams/4/4.png'],
        'Chennai Super Kings' => ['code' => 'CSK', 'logo' => 'https://cdn.sportmonks.com/images/cricket/teams/5/5.png'],
        'Royal Challengers Bengaluru' => ['code' => 'RCB', 'logo' => 'https://cdn.sportmonks.com/images/cricket/teams/8/8.png'],
        'Sunrisers Hyderabad' => ['code' => 'SRH', 'logo' => 'https://cdn.sportmonks.com/images/cricket/teams/9/9.png'],
        'Kolkata Knight Riders' => ['code' => 'KKR', 'logo' => 'https://cdn.sportmonks.com/images/cricket/teams/6/6.png'],
        'Delhi Capitals' => ['code' => 'DC', 'logo' => 'https://cdn.sportmonks.com/images/cricket/teams/3/3.png'],
        'Punjab Kings' => ['code' => 'PBKS', 'logo' => 'https://cdn.sportmonks.com/images/cricket/teams/7/7.png'],
        'Rajasthan Royals' => ['code' => 'RR', 'logo' => 'https://cdn.sportmonks.com/images/cricket/teams/1/1.png'],
        'Lucknow Super Giants' => ['code' => 'LSG', 'logo' => 'https://cdn.sportmonks.com/images/cricket/teams/14/14.png'],
        'Gujarat Titans' => ['code' => 'GT', 'logo' => 'https://cdn.sportmonks.com/images/cricket/teams/15/15.png'],
    ];

    if (isset($apiResponse['data'])) {

        foreach ($apiResponse['data'] as $match) {

            $matchTime = \Carbon\Carbon::parse($match['starting_at']);

            // ✅ STATUS FIX
            $status = 'upcoming';

            if (($match['status'] ?? '') == 'Finished') {
                $status = 'completed';
            } 
            elseif ($matchTime->isPast() && ($match['live'] ?? false) == true) {
                $status = 'live';
            }

            // 🔥 TEAM NAMES
            $team1_name = $match['localteam']['name'] ?? '';
            $team2_name = $match['visitorteam']['name'] ?? '';

            $team1_code = !empty($match['localteam']['code'])
    ? $match['localteam']['code']
    : ($teamMap[$team1_name]['code'] ?? '');

$team2_code = !empty($match['visitorteam']['code'])
    ? $match['visitorteam']['code']
    : ($teamMap[$team2_name]['code'] ?? '');

$team1_logo = !empty($match['localteam']['image_path'])
    ? $match['localteam']['image_path']
    : ($teamMap[$team1_name]['logo'] ?? '');

$team2_logo = !empty($match['visitorteam']['image_path'])
    ? $match['visitorteam']['image_path']
    : ($teamMap[$team2_name]['logo'] ?? '');

            // 🔥 RUNS
            // 🔥 RUNS (FIXED - NO SWAP ISSUE)
$runs = $match['runs'] ?? [];

$team1_score = 0;
$team1_wicket = 0;
$team1_over = '0.0';

$team2_score = 0;
$team2_wicket = 0;
$team2_over = '0.0';

foreach ($runs as $run) {

    // ✅ Match with LOCAL TEAM
    if ($run['team_id'] == $match['localteam_id']) {
        $team1_score = $run['score'] ?? 0;
        $team1_wicket = $run['wickets'] ?? 0;
        $team1_over = $run['overs'] ?? '0.0';
    }

    // ✅ Match with VISITOR TEAM
    if ($run['team_id'] == $match['visitorteam_id']) {
        $team2_score = $run['score'] ?? 0;
        $team2_wicket = $run['wickets'] ?? 0;
        $team2_over = $run['overs'] ?? '0.0';
    }
}

            // 🔥 RESULT
            $result_note = $match['note'] ?? '';

            // 🔥 SAVE
            CricketMatch::updateOrCreate(
                ['api_match_id' => $match['id']],
                [
                    'series_name' => $match['league']['name'] ?? 'IPL',

                    'team_1' => $team1_name,
                    'team_2' => $team2_name,

                    'team1_code' => $team1_code,
                    'team2_code' => $team2_code,

                    'team1_logo' => $team1_logo,
                    'team2_logo' => $team2_logo,

                    'match_start_time' => \Carbon\Carbon::parse($match['starting_at'])->utc(),

                    'status' => $status,

                    'result_note' => $result_note,

                    'team1_score' => $team1_score,
                    'team1_wicket' => $team1_wicket,
                    'team1_over' => $team1_over,

                    'team2_score' => $team2_score,
                    'team2_wicket' => $team2_wicket,
                    'team2_over' => $team2_over,
                ]
            );
        }
    }

    return response()->json([
        'status' => true,
        'message' => 'Matches synced successfully'
    ]);
}

 public function homeMatches()
{
    $matches = CricketMatch::whereIn('status',['live','completed','upcoming'])

        // ✅ SHOW yesterday + today (IST SAFE)
        ->whereBetween('match_start_time', [
            now('Asia/Kolkata')->subDay()->startOfDay()->utc(),
            now('Asia/Kolkata')->endOfDay()->utc()
        ])

        // ✅ PRIORITY: live → upcoming → completed
        ->orderByRaw("FIELD(status,'live','upcoming','completed')")
        ->orderBy('match_start_time','desc')
        ->limit(10)
        ->get();

    $data = [];

    foreach($matches as $match){

        // ✅ CONVERT TO IST
        $matchTimeIST = \Carbon\Carbon::parse($match->match_start_time)
            ->setTimezone('Asia/Kolkata');

        // ✅ CLEAN RESULT TEXT
        $resultText = '';

        if (!empty($match->result_note)) {
            $resultText = str_replace(
                [$match->team_1, $match->team_2],
                [$match->team1_code, $match->team2_code],
                $match->result_note
            );
        } elseif (!empty($match->winner)) {
            $resultText = $match->winner . ' won';
        }

        $data[] = [

            'match_id' => $match->api_match_id,
            'match_name' => $match->series_name,

            'team1' => $match->team_1,
            'team2' => $match->team_2,

            'team1_code' => $match->team1_code,
            'team2_code' => $match->team2_code,

            'team1_logo' => $match->team1_logo,
            'team2_logo' => $match->team2_logo,

            'team1_score' => $match->team1_score ?? 0,
            'team1_wicket' => $match->team1_wicket ?? 0,
            'team1_over' => $match->team1_over ?? '0.0',

            'team2_score' => $match->team2_score ?? 0,
            'team2_wicket' => $match->team2_wicket ?? 0,
            'team2_over' => $match->team2_over ?? '0.0',

            'winner' => $match->winner,
            'status' => $match->status,

            // ✅ IST TIME
            'match_time_ist' => $matchTimeIST->format('h:i A'),
            'match_date_ist' => $matchTimeIST->format('d M Y'),

            // ✅ RESULT
            'result' => $resultText
        ];
    }

    return response()->json([
        'status' => true,
        'data' => $data
    ]);
}
   public function upcomingMatches()
{
    $matches = CricketMatch::where(
        'match_start_time', '>', now()->utc()
    )
    ->orderBy('match_start_time','asc')
    ->limit(10)
    ->get();

    $data = [];

    foreach($matches as $match){

        // ✅ IST TIME CONVERSION
        $matchTimeIST = \Carbon\Carbon::parse($match->match_start_time)
            ->setTimezone('Asia/Kolkata');

        $data[] = [

            // ✅ TEAM SHORT NAME
            'team1_code' => $match->team1_code ?? '',
            'team2_code' => $match->team2_code ?? '',

            // ✅ TEAM LOGO (SAFE FALLBACK)
            'team1_logo' => $match->team1_logo ?: 'https://h.cricapi.com/img/icon512.png',
            'team2_logo' => $match->team2_logo ?: 'https://h.cricapi.com/img/icon512.png',

            // ✅ MATCH TIME (IST)
            'match_time' => $matchTimeIST->format('h:i A'),

            // ✅ MATCH DATE
            'match_date' => $matchTimeIST->format('d M'),

            // ✅ STATUS
            'status' => 'upcoming'
        ];
    }

    return response()->json([
        'status' => true,
        'data' => $data
    ]);
}

   


public function matchInfo($id, CricketApiService $service)
{
    $response = $service->getMatchInfo($id);

    if (!isset($response['data'])) {
        return response()->json([
            'status' => false,
            'message' => 'Match info not available'
        ]);
    }

    $data = $response['data'];

    // =========================
    // ✅ BASIC INFO
    // =========================
    $team1 = $data['localteam'] ?? [];
    $team2 = $data['visitorteam'] ?? [];

    $matchTime = $data['starting_at'] ?? null;

    $istTime = $matchTime
        ? Carbon::parse($matchTime)->setTimezone('Asia/Kolkata')
        : null;

    // =========================
    // ✅ VENUE
    // =========================
    $venue = $data['venue'] ?? [];

    // =========================
    // ✅ SQUADS (ONLY NAMES)
    // =========================
    $team1Names = [];
    $team2Names = [];

    if (!empty($data['lineup'])) {
        foreach ($data['lineup'] as $player) {

            $teamId = $player['lineup']['team_id'] ?? null;
            $name = $player['fullname'] ?? '';

            // 👉 Add (c) for captain
            if (($player['lineup']['captain'] ?? false) === true) {
                $name .= ' (c)';
            }

            if ($teamId == ($team1['id'] ?? null)) {
                $team1Names[] = $name;
            }

            if ($teamId == ($team2['id'] ?? null)) {
                $team2Names[] = $name;
            }
        }
    }

    // =========================
    // ✅ FINAL RESPONSE
    // =========================
    return response()->json([
        'status' => true,
        'data' => [

            // 🏏 Match Header
            'match' => [
                'team1' => [
                    'name' => $team1['name'] ?? '',
                    'short_name' => $team1['code'] ?? '',
                    'logo' => $team1['image_path'] ?? ''
                ],
                'team2' => [
                    'name' => $team2['name'] ?? '',
                    'short_name' => $team2['code'] ?? '',
                    'logo' => $team2['image_path'] ?? ''
                ],
                'status' => $data['status'] ?? '',
                'note' => $data['note'] ?? '',
                'time' => $istTime ? $istTime->format('h:i A') : '',
                'date' => $istTime ? $istTime->format('l, M d') : '',
                'timestamp' => $istTime
            ],

            // 📊 Match Details
            'details' => [
                'series' => $data['league']['name'] ?? '',
                'match_type' => strtoupper($data['type'] ?? ''),
                'toss' => $data['toss_won_team_id']
                    ? (($data['toss_won_team_id'] == ($team1['id'] ?? null))
                        ? ($team1['name'] . ' won the toss')
                        : ($team2['name'] . ' won the toss'))
                    : 'Yet to happen'
            ],

            // 🏟 Venue
            'venue' => [
                'name' => $venue['name'] ?? '',
                'city' => $venue['city'] ?? '',
                'country' => $venue['country'] ?? '',
                'full' => ($venue['name'] ?? '') . ', ' . ($venue['city'] ?? ''),
                'pitch' => 'Batting',
                'support' => 'Pacers',
                'batting_first_win' => 40,
                'batting_second_win' => 60
            ],

            // 🤝 Head-to-Head (static for now)
            'head_to_head' => [
                'team1_wins' => 0,
                'team2_wins' => 0
            ],

            // 👥 Squads (ONLY NAMES STRING)
            'squads' => [
                'team1' => [
                    'name' => $team1['name'] ?? '',
                    'players' => implode(', ', $team1Names)
                ],
                'team2' => [
                    'name' => $team2['name'] ?? '',
                    'players' => implode(', ', $team2Names)
                ]
            ]
        ]
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

    // ✅ Step 1: Get match
    $match = CricketMatch::where('api_match_id', $id)->first();

    if (!$match) {
        return response()->json([
            'status' => false,
            'message' => 'Match not found'
        ]);
    }

    $teams = $response['data'];

    $team1 = $teams[0] ?? [];
    $team2 = $teams[1] ?? [];

    // ✅ Helper function to save players PROPERLY
    $formatPlayers = function ($players, $teamShort) use ($match) {

        return collect($players)->map(function ($p) use ($match, $teamShort) {

            // 🔥 FIX: UNIQUE BY api_player_id + match_id
            $player = Player::updateOrCreate(
                [
                    'api_player_id' => $p['id'],
                    'cricket_match_id' => $match->id
                ],
                [
                    'name' => $p['name'] ?? '',
                    'role' => $this->mapRole($p['role'] ?? ''),
                    'team_name' => $teamShort ?? '',
                    'image' => $p['playerImg'] ?? 'https://h.cricapi.com/img/icon512.png',
                    'credit' => 8
                ]
            );

            return [
                'id' => $p['id'], // UUID
                'player_id' => $player->id, // ✅ USE THIS IN FRONTEND
                'name' => $player->name,
                'role' => $player->role,
                'image' => $player->image,
                'credit' => $player->credit,
                'points' => $player->points ?? 0,
            ];
        })->values();
    };

    return response()->json([
        'status' => true,
        'data' => [

            'team1' => [
                'name' => $team1['teamName'] ?? '',
                'short' => $team1['shortname'] ?? '',
                'players' => $formatPlayers($team1['players'] ?? [], $team1['shortname'] ?? '')
            ],

            'team2' => [
                'name' => $team2['teamName'] ?? '',
                'short' => $team2['shortname'] ?? '',
                'players' => $formatPlayers($team2['players'] ?? [], $team2['shortname'] ?? '')
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


public function playersList($match_id)
{
    // Step 1: Find match using API match ID
    $match = CricketMatch::where('api_match_id', $match_id)->first();

    if (!$match) {
        return response()->json([
            'status' => false,
            'message' => 'Match not found'
        ]);
    }

    // Step 2: Get players (for now all)
   $players = Player::where('cricket_match_id', $match->id)->get();

    // Step 3: Prepare role-wise data
    $data = [
        'wk' => [],
        'bat' => [],
        'all' => [],
        'bowl' => [],
    ];

    foreach ($players as $player) {

        $playerData = [
            'id' => $player->id,
            'name' => $player->name,

            // Role (WK / BAT / ALL / BOWL)
            'role' => $player->role,

            // Image
            'image' => $player->image ?? 'https://h.cricapi.com/img/icon512.png',

            // Team short name
            'team' => $player->team_name ?? '',

            // Credits (for UI)
            'credit' => $player->credit ?? rand(7,10),

            // Points (temporary random)
            'points' => $player->points ?? rand(0,100),

            // Selection %
            'selection_percentage' => rand(1,100),
        ];

        // Step 4: Group by role
        switch ($player->role) {
            case 'WK':
                $data['wk'][] = $playerData;
                break;

            case 'BAT':
                $data['bat'][] = $playerData;
                break;

            case 'ALL':
                $data['all'][] = $playerData;
                break;

            case 'BOWL':
                $data['bowl'][] = $playerData;
                break;
        }
    }

    // Step 5: Return response
    return response()->json([
        'status' => true,
        'data' => $data
    ]);
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

//     public function homeContests()
// {

//     $contests = \App\Models\Contest::with('match')
//         ->where('status','upcoming')
//         ->limit(5)
//         ->get()
//         ->map(function ($contest) {

//             $match = $contest->match;

//             return [

//                 'contest_id' => $contest->id,

//                 'contest_name' => $contest->name,

//                 'entry_fee' => $contest->entry_fee,

//                 'prize' => $contest->prize_pool,

//                 'match' => [

//                     'match_id' => $match->api_match_id,

//                     'series' => $match->series_name,

//                     'team1' => $match->team_1,

//                     'team2' => $match->team_2,

//                     'match_time' => $match->match_start_time,

//                     'time' => $match->match_start_time->format('h:i A')

//                 ]

//             ];
//         });

//     return response()->json([
//         'status' => true,
//         'data' => $contests
//     ]);
// }

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
    $info = $service->getMatchInfo($id);
    $bbb  = $service->getBallByBall($id);

    $match = $info['data'] ?? [];

    // =========================
    // ✅ TEAMS
    // =========================
    $team1 = $match['localteam'] ?? [];
    $team2 = $match['visitorteam'] ?? [];

    // =========================
    // ✅ SCORE
    // =========================
    $runs = $match['runs'] ?? [];

    $team1Score = [];
    $team2Score = [];

    if (is_array($runs)) {
        foreach ($runs as $run) {

            if (($run['team_id'] ?? null) == ($match['localteam_id'] ?? null)) {
                $team1Score = $run;
            }

            if (($run['team_id'] ?? null) == ($match['visitorteam_id'] ?? null)) {
                $team2Score = $run;
            }
        }
    }

    $score = [
        'team1' => [
            'name' => $team1['code'] ?? '',
            'runs' => $team1Score['score'] ?? 0,
            'wickets' => $team1Score['wickets'] ?? 0,
            'overs' => $team1Score['overs'] ?? '0'
        ],
        'team2' => [
            'name' => $team2['code'] ?? '',
            'runs' => $team2Score['score'] ?? 0,
            'wickets' => $team2Score['wickets'] ?? 0,
            'overs' => $team2Score['overs'] ?? '0'
        ]
    ];

    // =========================
    // ✅ BALL DATA
    // =========================
    $balls = [];

    if (isset($bbb['data']['balls'])) {
        $balls = $bbb['data']['balls'];
    }

    $currentBatsmen = [];
    $currentBowler = null;
    $lastOver = [];

    if (!empty($balls)) {

        $lastBall = end($balls);

        $currentBowler = $lastBall['bowler']['fullname'] ?? null;

        $currentOver = $lastBall['over'] ?? 0;

        $overBalls = array_filter($balls, fn($b) => ($b['over'] ?? null) == $currentOver);

        foreach ($overBalls as $b) {
            $lastOver[] = $b['score'] ?? '.';
        }

        $bats = [];

        foreach (array_reverse($balls) as $b) {
            $name = $b['batsman']['fullname'] ?? null;

            if ($name && !in_array($name, $bats)) {
                $bats[] = $name;
            }

            if (count($bats) == 2) break;
        }

        foreach ($bats as $b) {
            $currentBatsmen[] = ['name' => $b];
        }
    }

    // =========================
    // ✅ 🔥 SQUAD FIX (IMPORTANT)
    // =========================
    $team1Squad = [];
    $team2Squad = [];

    $lineup = $match['lineup'] ?? [];

    if (is_array($lineup)) {
        foreach ($lineup as $player) {

            $playerName = $player['fullname'] ?? '';

            if (($player['lineup']['team_id'] ?? null) == ($match['localteam_id'] ?? null)) {
                $team1Squad[] = $playerName;
            }

            if (($player['lineup']['team_id'] ?? null) == ($match['visitorteam_id'] ?? null)) {
                $team2Squad[] = $playerName;
            }
        }
    }

    // =========================
    // ✅ FINAL RESPONSE
    // =========================
    return response()->json([
        'status' => true,
        'data' => [

            'match_name' => ($team1['name'] ?? '') . ' vs ' . ($team2['name'] ?? ''),
            'match_status' => $match['status'] ?? '',
            'venue' => '',

            'teams' => [
                'team1' => $team1,
                'team2' => $team2
            ],

            'score' => $score,

            'last_over' => $lastOver,

            'batsmen' => $currentBatsmen,

            'bowler' => $currentBowler,

            'toss' => null,

            // ✅ NOW WILL WORK
            'team1_squad' => $team1Squad,
            'team2_squad' => $team2Squad
        ]
    ]);
}

public function scorecard($id, CricketApiService $service)
{
    // ✅ Use getMatchInfo with batting/bowling included
    $response = $service->getScorecardData($id);

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

    // ✅ INNINGS from 'runs' (Sportmonks structure)
    foreach ($data['runs'] ?? [] as $s) {
        $innings[] = [
            'team'    => $s['team']['name'] ?? ($s['team_id'] ?? ''),
            'runs'    => $s['score'] ?? 0,
            'wickets' => $s['wickets'] ?? 0,
            'overs'   => $s['overs'] ?? '0.0'
        ];
    }

    // ✅ GROUP batting by innings number
    $battingByInning = [];
    foreach ($data['batting'] ?? [] as $b) {
        $inningNum = $b['scoreboard'] ?? 1; // S1, S2 etc
        $battingByInning[$inningNum][] = $b;
    }

    // ✅ GROUP bowling by innings number
    $bowlingByInning = [];
    foreach ($data['bowling'] ?? [] as $b) {
        $inningNum = $b['scoreboard'] ?? 1;
        $bowlingByInning[$inningNum][] = $b;
    }

    $inningKeys = array_unique(
        array_merge(
            array_keys($battingByInning),
            array_keys($bowlingByInning)
        )
    );
    sort($inningKeys);

    foreach ($inningKeys as $inningKey) {

        $battingList  = [];
        $bowlingList  = [];
        $fow          = [];
        $teamRuns     = 0;
        $wicketCount  = 0;

        // ✅ BATTING
       foreach ($battingByInning[$inningKey] ?? [] as $b) {

    $playerName = $b['batsman']['fullname']
        ?? ($b['batsman']['firstname'] ?? '');

    if ($b['active'] == true) {
        $dismissal = 'batting';
    } elseif (empty($b['wicket_id'])) {
        $dismissal = 'not out';
    } else {
        $dismissal = 'out';
    }

    $battingList[] = [
        'name'        => $playerName,
        'dismissal'   => $dismissal,
        'runs'        => $b['score'] ?? 0,
        'balls'       => $b['ball'] ?? 0,
        'fours'       => $b['four_x'] ?? 0,
        'sixes'       => $b['six_x'] ?? 0,
        'strike_rate' => $b['rate'] ?? ''
    ];

    // ✅ FIXED FOW
    if (!empty($b['fow_score'])) {
        $fow[] = [
            'player' => $playerName,
            'score'  => $b['fow_score'],
            'over'   => $b['fow_balls'] ?? ''
        ];
    }
}

        // ✅ BOWLING
        foreach ($bowlingByInning[$inningKey] ?? [] as $b) {
            $bowlingList[] = [
                'name'    => $b['player']['fullname'] ?? ($b['player']['name'] ?? ''),
                'overs'   => $b['overs'] ?? '',
                'maidens' => $b['medians'] ?? 0,   // Sportmonks uses 'medians'
                'runs'    => $b['runs'] ?? 0,
                'wickets' => $b['wickets'] ?? 0,
                'economy' => $b['rate'] ?? ''
            ];
        }

        $batting[]        = $battingList;
        $bowling[]        = $bowlingList;
        $fallOfWickets[]  = $fow;
        $extras[]         = ['runs' => 0, 'byes' => 0]; // Sportmonks doesn't expose extras easily
    }

    // ✅ SAME response structure — app needs zero changes
    return response()->json([
        'status' => true,
        'data' => [
            'innings'          => $innings,
            'batting'          => $batting,
            'bowling'          => $bowling,
            'extras'           => $extras,
            'fall_of_wickets'  => $fallOfWickets
        ]
    ]);
}

public function debugScorecard($id, CricketApiService $service)
{
    $response = $service->getRawScorecard($id);
    
    // Show first batting entry raw
    $firstBat = $response['data']['batting'][0] ?? 'no batting data';
    $firstBowl = $response['data']['bowling'][0] ?? 'no bowling data';
    
    return response()->json([
        'first_batting_raw' => $firstBat,
        'first_bowling_raw' => $firstBowl,
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

    $data = $response['data'];

    $team1 = $data['localteam'] ?? [];
    $team2 = $data['visitorteam'] ?? [];
    $lineup = $data['lineup'] ?? [];

    $team1Players = [];
    $team2Players = [];

    if (is_array($lineup)) {
        foreach ($lineup as $player) {

            // 🔍 DEBUG (use once)
            // dd($player);

            // =========================
            // ✅ ROLE MAPPING (STRONG)
            // =========================
            $position = strtolower(
                $player['lineup']['position'] ??
                $player['position']['name'] ??
                $player['type'] ??
                ''
            );

            $role = 'BAT'; // default

            if (str_contains($position, 'keeper') || str_contains($position, 'wk')) {
                $role = 'WK';
            } elseif (str_contains($position, 'allround') || str_contains($position, 'all-round')) {
                $role = 'AR';
            } elseif (str_contains($position, 'bowler')) {
                $role = 'BOWL';
            } elseif (str_contains($position, 'bat')) {
                $role = 'BAT';
            }

            // =========================
            // ✅ PLAYER FORMAT
            // =========================
            $formattedPlayer = [
                'id' => $player['id'] ?? null,
                'name' => $player['fullname'] ?? '',
                'role' => $role,
                'image' => $player['image_path'] ?? null,
                'country' => $player['country_id'] ?? null
            ];

            // =========================
            // ✅ TEAM ASSIGNMENT
            // =========================
            if (($player['lineup']['team_id'] ?? null) == ($team1['id'] ?? null)) {
                $team1Players[] = $formattedPlayer;
            }

            if (($player['lineup']['team_id'] ?? null) == ($team2['id'] ?? null)) {
                $team2Players[] = $formattedPlayer;
            }
        }
    }

    return response()->json([
        'status' => true,
        'data' => [
            'team1' => [
                'name' => $team1['name'] ?? '',
                'short_name' => $team1['code'] ?? '',
                'logo' => $team1['image_path'] ?? '',
                'players' => array_values($team1Players)
            ],
            'team2' => [
                'name' => $team2['name'] ?? '',
                'short_name' => $team2['code'] ?? '',
                'logo' => $team2['image_path'] ?? '',
                'players' => array_values($team2Players)
            ]
        ]
    ]);
}
}