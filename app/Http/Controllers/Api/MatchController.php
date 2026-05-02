<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CricketMatch;
use App\Services\CricketApiService;
use Illuminate\Support\Facades\Cache;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

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
    $matches = \App\Models\CricketMatch::whereIn('status', ['live', 'completed'])

        // ✅ IPL ONLY
        ->where('series_name', 'like', '%Indian Premier League%')

        // ✅ Yesterday + Today (IST SAFE)
        ->whereBetween('match_start_time', [
            now('Asia/Kolkata')->subDay()->startOfDay()->utc(),
            now('Asia/Kolkata')->endOfDay()->utc()
        ])

        ->orderByRaw("FIELD(status,'live','upcoming','completed')")
        ->orderBy('match_start_time', 'desc')
        ->limit(10)
        ->get();

    $data = [];

    foreach ($matches as $match) {

        $matchTimeIST = \Carbon\Carbon::parse($match->match_start_time)
            ->setTimezone('Asia/Kolkata');

        // =====================================
        // 🔥 FINAL RESULT LOGIC (FIXED)
        // =====================================
        $resultText = '';

        if ($match->status === 'completed') {

            // ✅ PRIORITY 1: USE API RESULT (BEST)
            if (!empty($match->result_note)) {

                $resultText = str_replace(
                    [$match->team_1, $match->team_2],
                    [$match->team1_code, $match->team2_code],
                    $match->result_note
                );

            } else {

                // ✅ FALLBACK (CORRECT LOGIC)
                $team1Score = $match->team1_score ?? 0;
                $team2Score = $match->team2_score ?? 0;

                // 👉 CASE 1: TEAM 2 WON (CHASED TARGET)
                if ($team2Score > $team1Score) {

                    $wicketsLeft = 10 - ($match->team2_wicket ?? 0);
                    $resultText = $match->team2_code . " won by {$wicketsLeft} wickets";

                }
                // 👉 CASE 2: TEAM 1 WON (DEFENDED)
                elseif ($team1Score > $team2Score) {

                    $runs = $team1Score - $team2Score;
                    $resultText = $match->team1_code . " won by {$runs} runs";

                }
                // 👉 CASE 3: TIE
                else {
                    $resultText = "Match tied";
                }
            }
        }

        $data[] = [

            'match_id' => $match->id,
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

            // ✅ FINAL RESULT
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
    $matches = CricketMatch::where('match_start_time', '>', now()->utc())
        ->where('series_name', 'Indian Premier League')
        ->orderBy('match_start_time', 'asc')
        ->limit(10)
        ->get();

    $data = [];

    foreach ($matches as $match) {

        $matchTimeIST = \Carbon\Carbon::parse($match->match_start_time)
            ->setTimezone('Asia/Kolkata');

        $data[] = [
            'match_id'   => $match->id,

            'team1_code' => $match->team1_code ?? '',
            'team2_code' => $match->team2_code ?? '',

            'team1_logo' => $match->team1_logo ?: 'https://h.cricapi.com/img/icon512.png',
            'team2_logo' => $match->team2_logo ?: 'https://h.cricapi.com/img/icon512.png',

            'match_time' => $matchTimeIST->format('h:i A'),
            'match_date' => $matchTimeIST->format('d M'),

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
    // =========================
    // ✅ STEP 1: FIND MATCH (DB ID)
    // =========================
    $match = CricketMatch::find($id);

    if (!$match) {
        return response()->json([
            'status' => false,
            'message' => 'Match not found'
        ]);
    }

    // =========================
    // ✅ STEP 2: CALL API USING api_match_id
    // =========================
    $response = $service->getMatchInfo($match->api_match_id);

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

            // Captain tag
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

            // 👥 Squads
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
    $match = CricketMatch::find($id);

    if (!$match) {
        return response()->json([
            'status' => false,
            'message' => 'Match not found'
        ]);
    }

    // 🔥 IMPORTANT: ALWAYS REFRESH (avoid stale wrong data)
    Player::where('cricket_match_id', $id)->delete();

    try {
        $response = $service->getMatchSquad($match->api_match_id);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => false,
            'message' => 'API error'
        ]);
    }

    if (!isset($response['data'])) {
        return response()->json([
            'status' => false,
            'message' => 'No data'
        ]);
    }

    $data = $response['data'];
    $playersToInsert = [];
    $lineupAvailable = !empty($data['lineup']);

    // =========================================
    // 🟢 CASE 1: LINEUP
    // =========================================
    if ($lineupAvailable) {

        foreach ($data['lineup'] as $player) {

            $teamId = $player['lineup']['team_id'] ?? null;

            // ✅ SAFE TEAM MAPPING
            if ($teamId == $data['localteam_id']) {
                $teamCode = $match->team1_code;
            } elseif ($teamId == $data['visitorteam_id']) {
                $teamCode = $match->team2_code;
            } else {
                // fallback (rare case)
                $teamCode = $match->team1_code;
            }

            $playersToInsert[] = $this->preparePlayer($player, $teamCode, $id, true);
        }

    } else {

        // =========================================
        // 🔴 CASE 2: SQUAD
        // =========================================
        try {
            $team1 = $service->getTeamSquad($data['localteam_id'], $data['season_id']);
            $team2 = $service->getTeamSquad($data['visitorteam_id'], $data['season_id']);

            $team1Squad = $team1['data']['squad'] ?? [];
            $team2Squad = $team2['data']['squad'] ?? [];

            // ✅ ASSIGN TEAM MANUALLY (IMPORTANT)
            foreach ($team1Squad as $player) {
                $playersToInsert[] = $this->preparePlayer($player, $match->team1_code, $id, false);
            }

            foreach ($team2Squad as $player) {
                $playersToInsert[] = $this->preparePlayer($player, $match->team2_code, $id, false);
            }

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Squad error'
            ]);
        }
    }

    Player::insert($playersToInsert);

    $players = Player::where('cricket_match_id', $id)->get();

    $final = $this->formatPlayersFromDB($players)->getData(true);
    $final['lineup_available'] = $lineupAvailable;

    return response()->json($final);
}

private function preparePlayer($player, $teamCode, $matchId, $isLineup)
{
    $roleName = strtolower($player['position']['name'] ?? '');

    if (str_contains($roleName, 'wicket')) $role = 'wk';
    elseif (str_contains($roleName, 'allround')) $role = 'ar';
    elseif (str_contains($roleName, 'bowl')) $role = 'bowl';
    else $role = 'bat';

    return [
        'cricket_match_id' => $matchId,
        'api_player_id' => $player['id'],
        'name' => $player['fullname'] ?? '',
        'team_code' => $teamCode,
        'role' => $role,
        'credit' => 8,
        'points' => 0,
        'selection_percentage' => 0,
        'image' => $player['image_path'] ?? null,

        'is_playing' => $isLineup
            ? !($player['lineup']['substitution'] ?? false)
            : 0,

        'is_captain' => $isLineup
            ? ($player['lineup']['captain'] ?? 0)
            : 0,

        'is_wk' => $isLineup
            ? ($player['lineup']['wicketkeeper'] ?? 0)
            : 0,

        'created_at' => now(),
        'updated_at' => now(),
    ];
}

private function formatPlayersFromDB($players): \Illuminate\Http\JsonResponse
{
    // ✅ Get all player IDs
    $playerIds = $players->pluck('id')->toArray();

    // ✅ Fetch total points in ONE query (avoid N+1)
    $pointsMap = \App\Models\PlayerMatchPoint::whereIn('player_id', $playerIds)
        ->selectRaw('player_id, SUM(points) as total_points')
        ->groupBy('player_id')
        ->pluck('total_points', 'player_id');

    $result = ['wk' => [], 'bat' => [], 'ar' => [], 'bowl' => []];

    $lineupAvailable = $players->contains('is_playing', 1);

    foreach ($players as $p) {

        $totalPoints = (float) ($pointsMap[$p->id] ?? 0);

        $role = strtolower($p->role ?? 'bat');
        if (!array_key_exists($role, $result)) {
            $role = 'bat';
        }

        $result[$role][] = [
            'id'                   => $p->api_player_id,
            'name'                 => $p->name,
            'team'                 => $p->team_code,
            'image'                => $p->image,
            'credit'               => (float) $p->credit,

            // ✅ Points
            'points'               => (float) ($p->points ?? 0),
            'total_points'         => $totalPoints,

            // ✅ Selection %
            'selection_percentage' => (float) ($p->selection_percentage ?? 0),

            'is_playing'           => (bool) $p->is_playing,
            'is_captain'           => (bool) $p->is_captain,
            'is_wk'                => (bool) $p->is_wk,
            'substitution'         => (bool) $p->substitution,
        ];
    }

    // ✅ SORT EACH ROLE BY TOTAL POINTS DESC
    foreach ($result as $role => $playersList) {
        $result[$role] = collect($playersList)
            ->sortByDesc('total_points')
            ->values()
            ->toArray();
    }

    return response()->json([
        'status'           => true,
        'lineup_available' => $lineupAvailable,
        'source'           => 'database',
        'data'             => $result,
    ]);
}


public function refreshPlayers($id, CricketApiService $service)
{
    // Delete old players for this match
    Player::where('cricket_match_id', $id)->delete();
    
    // Re-fetch fresh from API
    return $this->players($id, $service);
}

// ================================================
// CREDIT SYSTEM — role based
// ================================================
private function resolveRole(string $positionName, bool $isWK = false): string
{
    if ($isWK || str_contains($positionName, 'wicketkeeper')) return 'wk';
    if (str_contains($positionName, 'allrounder'))            return 'ar';
    if (str_contains($positionName, 'bowler'))                return 'bowl';
    return 'bat';
}

private function getPlayerCredit(string $role): float
{
    return match($role) {
        'wk'   => 9.0,
        'ar'   => 8.5,
        'bowl' => 8.0,
        'bat'  => 8.0,
        default => 7.5,
    };
}

   public function homeContests()
{
    $contests = \App\Models\Contest::with('match')
        ->where('status', 'upcoming')
        ->whereHas('match', function ($query) {
            $query->where('status', 'upcoming');
        })
        ->limit(5)
        ->get()
        ->map(function ($contest) {

            $match = $contest->match;

            if (!$match) return null;

            // 🟢 SAFE TIME
            $matchTime = $match->match_start_time
                ? \Carbon\Carbon::parse($match->match_start_time)->setTimezone('Asia/Kolkata')
                : null;

            return [

                // 🎯 CONTEST
                'contest_id'   => $contest->id,
                'contest_name' => $contest->name,
                'entry_fee'    => (float) $contest->entry_fee,
                'prize_pool'   => (float) $contest->prize_pool,

                // 🏏 MATCH
                'match' => [
                    'match_id'   => $match->id,

                    // 🔥 SHORT NAMES (IMPORTANT)
                    'team1'      => $match->team1_code ?? strtoupper(substr($match->team_1, 0, 3)),
                    'team2'      => $match->team2_code ?? strtoupper(substr($match->team_2, 0, 3)),

                    // 🔥 FULL NAMES (OPTIONAL FOR UI)
                    'team1_full' => $match->team_1,
                    'team2_full' => $match->team_2,

                    // 🖼️ LOGOS
                    'team1_logo' => $match->team1_logo 
                        ?? 'https://h.cricapi.com/img/icon512.png',

                    'team2_logo' => $match->team2_logo 
                        ?? 'https://h.cricapi.com/img/icon512.png',

                    // 📅 SERIES
                    'series_name' => $match->series_name ?? '',

                    // 🕒 TIME
                    'match_time' => $matchTime?->format('h:i A'),
                    'match_date' => $matchTime?->format('d M'),

                    // ⏳ COUNTDOWN (BONUS 🔥)
                    'status_text' => $matchTime
                        ? 'Starts in ' . now()->diffForHumans($matchTime, true)
                        : '',
                ]
            ];
        })
        ->filter()
        ->values();

    return response()->json([
        'status' => true,
        'data'   => $contests
    ]);
}


public function playersList($match_id)
{
    // ✅ Find match
    $match = CricketMatch::where('api_match_id', $match_id)->first();

    if (!$match) {
        return response()->json([
            'status' => false,
            'message' => 'Match not found'
        ]);
    }

    // ✅ GET PLAYERS BY TEAM (FIXED)
    $players = Player::whereIn('team_name', [
        $match->team1_code,
        $match->team2_code
    ])->get();

    // =========================
    // ✅ ROLE GROUPING
    // =========================
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
            'role' => $player->role,
            'image' => $player->image ?? 'https://h.cricapi.com/img/icon512.png',
            'team' => $player->team_name ?? '',
            'credit' => $player->credit ?? 8,
            'points' => $player->points ?? 0,
            'selection_percentage' => rand(10,80),
        ];

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


public function live($id, CricketApiService $service)
{
    $matchModel = CricketMatch::find($id);

    if (!$matchModel) {
        return response()->json([
            'status'  => false,
            'message' => 'Match not found'
        ]);
    }

    $apiId    = $matchModel->api_match_id;
    $info     = $service->getMatchInfo($apiId);
    $liveData = $service->getBallByBall($apiId);

    if (!isset($info['data'])) {
        return response()->json([
            'status'  => false,
            'message' => 'Live data not available'
        ]);
    }

    $match = $info['data'];

    // =========================
    // TEAMS
    // =========================
    $team1 = $match['localteam']   ?? [];
    $team2 = $match['visitorteam'] ?? [];

    // =========================
    // SCORE
    // =========================
    $runs       = $match['runs'] ?? [];
    $team1Score = collect($runs)->firstWhere('team_id', $team1['id'] ?? null) ?? [];
    $team2Score = collect($runs)->firstWhere('team_id', $team2['id'] ?? null) ?? [];

    $t1_runs    = (int)   ($team1Score['score']   ?? 0);
    $t1_wickets = (int)   ($team1Score['wickets'] ?? 0);
    $t1_overs   = (float) ($team1Score['overs']   ?? $team1Score['overs_float'] ?? 0);

    $t2_runs    = (int)   ($team2Score['score']   ?? 0);
    $t2_wickets = (int)   ($team2Score['wickets'] ?? 0);
    $t2_overs   = (float) ($team2Score['overs']   ?? $team2Score['overs_float'] ?? 0);

    $totalOvers = (float) ($match['total_overs'] ?? 20); // T20 = 20

    // =========================
    // ✅ CRR — Current Run Rate
    // CRR = runs scored / overs bowled
    // =========================
    $crr = null;

    // Figure out which innings is live
    $matchStatus   = $match['status'] ?? '';
    $secondInnings = $t2_overs > 0; // PBKS batting = second innings live

    if ($secondInnings && $t2_overs > 0) {
        $crr = round($t2_runs / $t2_overs, 2);
    } elseif (!$secondInnings && $t1_overs > 0) {
        $crr = round($t1_runs / $t1_overs, 2);
    }

    // =========================
    // ✅ RRR — Required Run Rate
    // RRR = runs needed / overs remaining
    // Only in 2nd innings
    // =========================
    $rrr            = null;
    $runsNeeded     = null;
    $ballsRemaining = null;
    $oversRemaining = null;

    if ($secondInnings && $t1_runs > 0) {
        $target         = $t1_runs + 1;
        $runsNeeded     = $target - $t2_runs;
        $ballsRemaining = (int) (($totalOvers * 6) - ($t2_overs * 6));
        $oversRemaining = round($ballsRemaining / 6, 1);

        if ($ballsRemaining > 0 && $runsNeeded > 0) {
            $rrr = round(($runsNeeded / $ballsRemaining) * 6, 2);
        } elseif ($runsNeeded <= 0) {
            $rrr = 0; // already won
        }
    }

    // =========================
    // SCORE OBJECT
    // =========================
    $score = [
        'team1' => [
            'name'    => $team1['code'] ?? '',
            'runs'    => $t1_runs,
            'wickets' => $t1_wickets,
            'overs'   => $t1_overs,
        ],
        'team2' => [
            'name'    => $team2['code'] ?? '',
            'runs'    => $t2_runs,
            'wickets' => $t2_wickets,
            'overs'   => $t2_overs,
        ],
    ];

    // =========================
    // RESULT / LIVE MESSAGE
    // =========================
    $result = null;

    if ($matchStatus === 'Finished') {
        // Use Sportmonks note if available
        $result = $match['note'] ?? null;

        // Fallback: build manually
        if (!$result) {
            if ($t1_runs > $t2_runs) {
                $result = ($team1['code'] ?? 'Team 1') . ' won by ' . ($t1_runs - $t2_runs) . ' runs';
            } elseif ($t2_runs > $t1_runs) {
                $wicketsLeft = 10 - $t2_wickets;
                $result      = ($team2['code'] ?? 'Team 2') . ' won by ' . $wicketsLeft . ' wickets';
            } else {
                $result = 'Match tied';
            }
        }
    } else {
        // Live message
        if ($runsNeeded !== null && $ballsRemaining !== null && $runsNeeded > 0) {
            $result = ($team2['code'] ?? 'Team 2')
                . ' needs ' . $runsNeeded
                . ' runs in ' . $ballsRemaining . ' balls';
        }
    }

    // =========================
    // LIVE DATA
    // =========================
    $currentBatsmen = $liveData['current_batsmen'] ?? [];
    $currentBowler  = $liveData['current_bowler']  ?? null;
    $lastOver       = $liveData['recent_balls']    ?? [];

    // =========================
    // SQUAD
    // =========================
    $team1Squad = Player::where('cricket_match_id', $matchModel->id)
        ->where('team_name', $matchModel->team1_code)
        ->pluck('name');

    $team2Squad = Player::where('cricket_match_id', $matchModel->id)
        ->where('team_name', $matchModel->team2_code)
        ->pluck('name');

    // =========================
    // FINAL RESPONSE
    // =========================
    return response()->json([
        'status' => true,
        'data'   => [
            'match_name'   => ($team1['code'] ?? '') . ' vs ' . ($team2['code'] ?? ''),
            'match_status' => $matchStatus,
            'score'        => $score,
            'result'       => $result,

            // ✅ NEW FIELDS
            'crr'             => $crr,               // 8.25
            'rrr'             => $rrr,               // 11.40
            'target'          => $secondInnings ? ($t1_runs + 1) : null,
            'runs_needed'     => $runsNeeded,         // 45
            'balls_remaining' => $ballsRemaining,     // 24
            'overs_remaining' => $oversRemaining,     // 4.0
            'current_innings' => $secondInnings ? 2 : 1,

            'last_over'    => $lastOver,
            'batsmen'      => $currentBatsmen,
            'bowler'       => $currentBowler,
            'team1_squad'  => $team1Squad,
            'team2_squad'  => $team2Squad,
        ]
    ]);
}
public function scorecard($id, CricketApiService $service)
{
    $match = CricketMatch::find($id);

    if (!$match) {
        return response()->json([
            'status'  => false,
            'message' => 'Match not found'
        ]);
    }

    $response = $service->getScorecardData($match->api_match_id);

    if (!isset($response['data'])) {
        return response()->json([
            'status'  => false,
            'message' => 'Scorecard not available'
        ]);
    }

    $data = $response['data'];

    $status = $data['status'] ?? '';
    $result = $data['note']   ?? 'Match yet to start';

    // ================================================
    // TEAM CODES
    // ================================================
    $localteamId     = $data['localteam_id'] ?? null;
    $visitorteamId   = $data['visitorteam_id'] ?? null;
    $localteamCode   = $data['localteam']['code'] ?? $match->team1_code ?? '';
    $visitorteamCode = $data['visitorteam']['code'] ?? $match->team2_code ?? '';

    // ================================================
    // INNINGS
    // ================================================
    $innings = [];
    foreach ($data['runs'] ?? [] as $s) {
        $teamId = $s['team_id'] ?? null;

        if ($teamId == $localteamId) {
            $teamCode = $localteamCode;
        } elseif ($teamId == $visitorteamId) {
            $teamCode = $visitorteamCode;
        } else {
            $teamCode = $s['team']['code'] ?? $s['team']['name'] ?? '';
        }

        $innings[] = [
            'team'    => $teamCode,
            'runs'    => $s['score'] ?? 0,
            'wickets' => $s['wickets'] ?? 0,
            'overs'   => $s['overs'] ?? '0.0',
        ];
    }

    // ================================================
    // GROUPING
    // ================================================
    $battingByInning = [];
    foreach ($data['batting'] ?? [] as $b) {
        $battingByInning[$b['scoreboard'] ?? 'S1'][] = $b;
    }

    $bowlingByInning = [];
    foreach ($data['bowling'] ?? [] as $b) {
        $bowlingByInning[$b['scoreboard'] ?? 'S1'][] = $b;
    }

    // ================================================
    // FALL OF WICKETS
    // ================================================
    $fowByInning = [];
    foreach ($data['batting'] ?? [] as $b) {
        if (!empty($b['fow_score'])) {
            $key = $b['scoreboard'] ?? 'S1';
            $fowByInning[$key][] = [
                'player' => $b['batsman']['fullname'] ?? 'Unknown',
                'score'  => $b['fow_score'],
                'over'   => (float) ($b['fow_balls'] ?? 0),
            ];
        }
    }

    $inningKeys = array_unique(array_merge(
        array_keys($battingByInning),
        array_keys($bowlingByInning)
    ));
    sort($inningKeys);

    $batting = [];
    $bowling = [];
    $extras = [];
    $fallOfWickets = [];

    foreach ($inningKeys as $inningKey) {

        $battingList = [];
        $bowlingList = [];
        $fow = $fowByInning[$inningKey] ?? [];
        $outPlayers = array_column($fow, 'player');

        // ================================================
        // BATTING (FIXED DISMISSAL)
        // ================================================
        foreach ($battingByInning[$inningKey] ?? [] as $b) {

            $playerName = $b['batsman']['fullname']
                ?? $b['batsman']['name']
                ?? 'Unknown';

            $dismissal = 'not out';
            $isOut     = in_array($playerName, $outPlayers);

            // 🔥 PRIORITY 1: DIRECT TEXT FROM API
            if (!empty($b['dismissal_text'])) {
                $dismissal = $b['dismissal_text'];
            }

            // 🔥 PRIORITY 2: DESCRIPTION FIELD
            elseif (!empty($b['wicket']['description'])) {
                $dismissal = $b['wicket']['description'];
            }

            // 🔥 PRIORITY 3: BUILD MANUALLY
            elseif (!empty($b['wicket'])) {
                $w       = $b['wicket'];
                $type    = strtolower($w['type'] ?? $w['kind'] ?? '');
                $bowler  = $w['bowler']['fullname'] ?? $w['bowler']['name'] ?? '';
                $fielder = $w['fielder']['fullname'] ?? $w['catcher']['fullname'] ?? $w['fielder']['name'] ?? '';

                if ($type === 'bowled')          $dismissal = "b {$bowler}";
                elseif ($type === 'caught')      $dismissal = "c {$fielder} b {$bowler}";
                elseif ($type === 'run out')     $dismissal = "run out ({$fielder})";
                elseif ($type === 'lbw')         $dismissal = "lbw b {$bowler}";
                elseif ($type === 'stumped')     $dismissal = "st {$fielder} b {$bowler}";
                else                             $dismissal = ucfirst($type);
            }

            // 🔥 LAST FALLBACK
            elseif ($isOut) {
                $dismissal = 'out';
            }

            // Active batsman
            if (($b['active'] ?? false) === true) {
                $dismissal = 'not out';
            }

            $battingList[] = [
                'name'        => $playerName,
                'dismissal'   => $dismissal,
                'runs'        => $b['score'] ?? 0,
                'balls'       => $b['ball'] ?? 0,
                'fours'       => $b['four_x'] ?? 0,
                'sixes'       => $b['six_x'] ?? 0,
                'strike_rate' => $b['rate'] ?? '',
            ];
        }

        // ================================================
        // BOWLING
        // ================================================
        foreach ($bowlingByInning[$inningKey] ?? [] as $b) {

            $bowlerName = $b['player']['fullname']
                ?? $b['player']['name']
                ?? null;

            if (!$bowlerName && isset($b['player_id'])) {
                $player = Player::where('api_player_id', $b['player_id'])->first();
                $bowlerName = $player->name ?? 'Unknown';
            }

            $bowlingList[] = [
                'name'    => $bowlerName ?? 'Unknown',
                'overs'   => $b['overs'] ?? '',
                'maidens' => $b['medians'] ?? 0,
                'runs'    => $b['runs'] ?? 0,
                'wickets' => $b['wickets'] ?? 0,
                'economy' => $b['rate'] ?? '',
            ];
        }

        // ================================================
        // FALL OF WICKETS
        // ================================================
        usort($fow, fn($a, $b) => $a['over'] <=> $b['over']);

        $fowList = [];
        $i = 1;
        foreach ($fow as $f) {
            $fowList[] = [
                'player' => $f['player'],
                'score'  => $f['score'] . '-' . $i,
                'over'   => (string) $f['over'],
            ];
            $i++;
        }

        $batting[]       = $battingList;
        $bowling[]       = $bowlingList;
        $fallOfWickets[] = $fowList;
        $extras[]        = ['runs' => 0];
    }

    return response()->json([
        'status' => true,
        'data'   => [
            'match_result' => [
                'status' => $status,
                'result' => $result,
            ],
            'teams' => [
                'team1' => $localteamCode,
                'team2' => $visitorteamCode,
            ],
            'innings'         => $innings,
            'batting'         => $batting,
            'bowling'         => $bowling,
            'extras'          => $extras,
            'fall_of_wickets' => $fallOfWickets,
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


public function squads($id)
{
    $match = CricketMatch::find($id);

    if (!$match) {
        return response()->json([
            'status'  => false,
            'message' => 'Match not found'
        ]);
    }

    $apiKey   = config('services.sportmonks.key');
    $baseUrl  = config('services.sportmonks.base_url');

    // ================================================
    // FETCH FIXTURE
    // ================================================
    $response = Http::get("{$baseUrl}/fixtures/{$match->api_match_id}", [
        'api_token' => $apiKey,
        'include'   => 'lineup,localteam,visitorteam',
    ]);

    if (!$response->successful()) {
        return response()->json(['status' => false, 'message' => 'API failed']);
    }

    $data     = $response->json()['data'] ?? [];
    $lineup   = $data['lineup']           ?? [];
    $team1Id  = $data['localteam']['id']  ?? null;
    $team2Id  = $data['visitorteam']['id'] ?? null;
    $seasonId = $data['season_id']         ?? null;

    // ================================================
    // FORMAT HELPER
    // ================================================
    $format = function ($players) {
        return collect($players)->map(function ($p) {

            $positionName = strtolower($p['position']['name'] ?? '');
            $isWK         = $p['lineup']['wicketkeeper'] ?? false;

            if ($isWK || str_contains($positionName, 'wicketkeeper')) $role = 'WK';
            elseif (str_contains($positionName, 'allrounder'))         $role = 'AR';
            elseif (str_contains($positionName, 'bowler'))             $role = 'BOWL';
            else                                                        $role = 'BAT';

            return [
                'id'         => $p['id'],
                'name'       => $p['fullname'] ?? $p['name'] ?? 'Unknown',
                'role'       => $role,
                'credit'     => 8,
                'points'     => 0,
                'image'      => $p['image_path'] ?? 'https://cdn.sportmonks.com/images/cricket/placeholder.png',
                'is_captain' => $p['lineup']['captain']      ?? false,
                'is_wk'      => $p['lineup']['wicketkeeper'] ?? false,
            ];
        })->values();
    };

    // ================================================
    // CASE 1: LINEUP AVAILABLE → show playing 11 only
    // ================================================
    if (!empty($lineup)) {

        $players = collect($lineup);

        // Only playing 11 — no substitutes
        $team1Players = $players->filter(
            fn($p) => ($p['lineup']['team_id'] ?? null) == $team1Id
                   && !($p['lineup']['substitution'] ?? false)
        );

        $team2Players = $players->filter(
            fn($p) => ($p['lineup']['team_id'] ?? null) == $team2Id
                   && !($p['lineup']['substitution'] ?? false)
        );

        return response()->json([
            'status'           => true,
            'lineup_available' => true,
            'data' => [
                'team1' => [
                    'name'       => $data['localteam']['name'],
                    'short_name' => $data['localteam']['code'],
                    'logo'       => $data['localteam']['image_path'],
                    'players'    => $format($team1Players),
                ],
                'team2' => [
                    'name'       => $data['visitorteam']['name'],
                    'short_name' => $data['visitorteam']['code'],
                    'logo'       => $data['visitorteam']['image_path'],
                    'players'    => $format($team2Players),
                ],
            ]
        ]);
    }

    // ================================================
    // CASE 2: NO LINEUP → show full season squad
    // ================================================
    $team1SquadRes = Http::get("{$baseUrl}/teams/{$team1Id}/squad/{$seasonId}", [
        'api_token' => $apiKey,
    ]);

    $team2SquadRes = Http::get("{$baseUrl}/teams/{$team2Id}/squad/{$seasonId}", [
        'api_token' => $apiKey,
    ]);

    $team1Squad = $team1SquadRes->json()['data']['squad'] ?? [];
    $team2Squad = $team2SquadRes->json()['data']['squad'] ?? [];

    // Add empty lineup key so $format() works for both cases
    $withEmptyLineup = fn($squad) => array_map(function ($p) {
        $p['lineup'] = ['captain' => false, 'wicketkeeper' => false, 'substitution' => false];
        return $p;
    }, $squad);

    return response()->json([
        'status'           => true,
        'lineup_available' => false,
        'data' => [
            'team1' => [
                'name'       => $data['localteam']['name'],
                'short_name' => $data['localteam']['code'],
                'logo'       => $data['localteam']['image_path'],
                'players'    => $format($withEmptyLineup($team1Squad)),
            ],
            'team2' => [
                'name'       => $data['visitorteam']['name'],
                'short_name' => $data['visitorteam']['code'],
                'logo'       => $data['visitorteam']['image_path'],
                'players'    => $format($withEmptyLineup($team2Squad)),
            ],
        ]
    ]);
}

}