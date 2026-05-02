<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contest;
use App\Models\ContestParticipant;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\CricketMatch;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use App\Services\CricketApiService;

class ContestController extends Controller
{


  public function myPrivateContests()
{
    if (!auth()->check()) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthenticated'
        ]);
    }

    $userId = auth()->id();

    $contests = \App\Models\Contest::where('contest_type', 'private')
        ->where('created_by', $userId)
        ->with('match')
        ->get();

    $grouped = [
        'upcoming' => [],
        'live' => [],
        'completed' => []
    ];

    foreach ($contests as $contest) {

        $match = $contest->match ?? null;

        // 📊 CALCULATIONS
        $totalSlots = $contest->total_slots ?? 0;
        $filledSlots = $contest->filled_slots ?? 0;
        $spotsLeft = max($totalSlots - $filledSlots, 0);

        $progressPercent = $totalSlots > 0
            ? round(($filledSlots / $totalSlots) * 100)
            : 0;

        // 🕒 FORMAT TIME (IST)
        $matchTime = '';
        if ($match && $match->match_start_time) {
            $matchTime = \Carbon\Carbon::parse($match->match_start_time)
                ->setTimezone('Asia/Kolkata')
                ->format('h:i A');
        }

        // 📢 STATUS TEXT
        $statusText = match ($contest->status) {
            'upcoming' => 'Match starts soon',
            'live' => 'Live now',
            'completed' => 'Completed',
            default => ''
        };

        $item = [

            // 🔹 UNIQUE ID
            'id' => $contest->id,

            // 🔹 BASIC
            'contest_id' => $contest->id,
            'contest_name' => $contest->name,

            // 💰 ENTRY
            'entry_fee' => $contest->entry_fee,
            'is_free' => $contest->entry_fee == 0,
            'entry_label' => $contest->entry_fee == 0
                ? 'Free'
                : '₹' . $contest->entry_fee,

            'prize_pool' => $contest->prize_pool,

            // 📊 SLOTS
            'total_slots' => $totalSlots,
            'filled_slots' => $filledSlots,
            'spots_left' => $spotsLeft,
            'spots_left_text' => $spotsLeft . ' spots left',
            'progress_percent' => $progressPercent,

            // 👥 TEAM LIMIT
            'max_team_per_user' => $contest->max_team_per_user ?? 1,

            // 🏆 WINNERS
            'total_winners' => $contest->total_winners ?? 0,

            // 🔐 PRIVATE
            'private_code' => $contest->private_code,

            // 🏷 UI TEXT
            'contest_type_text' => 'Play for Pride',

            // 📩 INVITE
            'invite_code' => $contest->private_code,
            'invite_message' => "Join my contest using code: {$contest->private_code}",

            // 📍 STATUS
            'status' => $contest->status,
            'status_text' => $statusText,

            // 🏏 MATCH
            'match' => [
                'team1' => $match->team_1 ?? '',
                'team2' => $match->team_2 ?? '',
                'match_time' => $matchTime,
                'series_name' => $match->series_name ?? ''
            ]
        ];

        $grouped[$contest->status][] = $item;
    }

    return response()->json([
        'status' => true,
        'data' => $grouped
    ]);
}
    
   public function getMatchContests($matchId)
{
    $match = CricketMatch::find($matchId);

    if (!$match) {
        return response()->json(['status' => false, 'message' => 'Match not found']);
    }

    $userId = auth()->id();

    $contests = Contest::where('cricket_match_id', $match->id)
        ->whereIn('status', ['upcoming', 'live'])
        ->where('contest_type', 'public') // 🔥 ADD THIS LINE
        ->with('prizes')
        ->get()
        ->map(function ($contest) use ($match, $userId) {

            $userJoined = ContestParticipant::where('contest_id', $contest->id)
                ->where('user_id', $userId)
                ->exists();

            $progress = $contest->total_slots > 0
                ? round(($contest->filled_slots / $contest->total_slots) * 100, 2)
                : 0;

            return [
                'contest_id'        => $contest->id,
                'contest_name'      => $contest->name,
                'contest_badge'     => $contest->contest_badge ?? null,
                'contest_type'      => $contest->contest_type,
                'entry_fee'         => $contest->entry_fee,
                'prize_pool'        => $contest->prize_pool,
                'first_prize'       => $contest->first_prize ?? null,
                'total_slots'       => $contest->total_slots,
                'filled_slots'      => $contest->filled_slots,
                'spots_left'        => $contest->total_slots - $contest->filled_slots,
                'progress_percent'  => $progress,
                'max_team_per_user' => $contest->max_team_per_user,
                'total_winners'     => $contest->total_winners,
                'is_guaranteed'     => $contest->is_guaranteed ?? false,
                'status'            => $contest->status,
                'user_joined'       => $userJoined,
                'prize_breakup'     => $contest->prizes->map(function ($prize) {
                    return [
                        'rank_from'    => $prize->rank_from,
                        'rank_to'      => $prize->rank_to,
                        'prize_amount' => $prize->prize_amount,
                    ];
                }),
                'match' => [
                    'team1'      => $match->team_1,
                    'team2'      => $match->team_2,
                    'series'     => $match->series_name,
                    'match_time' => $match->match_start_time,
                ],
            ];
        });

    return response()->json([
        'status' => true,
        'data'   => $contests,
    ]);
}

   
    public function join(Request $request)
    {
        $request->validate([
            'contest_id'      => 'required|exists:contests,id',
            'fantasy_team_id' => 'required|exists:fantasy_teams,id',
        ]);

        $user = auth()->user();

        DB::beginTransaction();

        try {
            $contest = Contest::with('match')->lockForUpdate()->find($request->contest_id);

            if (!$contest) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Contest not found']);
            }

            // Match started check
            $match = $contest->match;
            if ($match && now() >= $match->match_start_time) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Contest locked. Match already started']);
            }

            // Contest full check
            if ($contest->filled_slots >= $contest->total_slots) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Contest is full']);
            }

            // Team ownership check
            $team = \App\Models\FantasyTeam::where('id', $request->fantasy_team_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$team) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Invalid team']);
            }

            // Same team already joined
            $alreadyJoined = ContestParticipant::where([
                'contest_id'      => $contest->id,
                'fantasy_team_id' => $request->fantasy_team_id,
            ])->exists();

            if ($alreadyJoined) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'This team already joined']);
            }

            // Max team per user check
            $userTeamCount = ContestParticipant::where('contest_id', $contest->id)
                ->where('user_id', $user->id)
                ->count();

            if ($userTeamCount >= $contest->max_team_per_user) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Max team limit reached']);
            }

            // Wallet check
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if (!$wallet || $wallet->deposit_balance < $contest->entry_fee) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Insufficient wallet balance']);
            }

            // Deduct balance
            $wallet->deposit_balance -= $contest->entry_fee;
            $wallet->save();

            // Log transaction
            WalletTransaction::create([
                'user_id'      => $user->id,
                'amount'       => $contest->entry_fee,
                'type'         => 'contest_entry',
                'wallet_type'  => 'deposit',
                'reference_id' => $contest->id,
                'description'  => 'Joined contest #' . $contest->id,
            ]);

            // Add participant
            ContestParticipant::create([
                'contest_id'      => $contest->id,
                'user_id'         => $user->id,
                'fantasy_team_id' => $request->fantasy_team_id,
                'total_points'    => 0,
                'rank'            => null,
            ]);

            // Increment slot
            $contest->increment('filled_slots');

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Contest joined successfully',
                'data'    => [
                    'contest_id'        => $contest->id,
                    'entry_fee'         => $contest->entry_fee,
                    'remaining_balance' => $wallet->deposit_balance,
                    'filled_slots'      => $contest->fresh()->filled_slots,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }


   public function createPrivateContest(Request $request)
{
    $request->validate([
        'cricket_match_id' => 'required',
        'name'             => 'required|string|max:100',
        'entry_fee'        => 'required|numeric|min:1',
        'total_slots'      => 'required|integer|min:2|max:100',
    ]);

    $user = auth()->user();

    if (!$user) {
        return response()->json(['status' => false, 'message' => 'Unauthenticated']);
    }

    $match = CricketMatch::find($request->cricket_match_id);

    if (!$match) {
        return response()->json(['status' => false, 'message' => 'Match not found']);
    }

   
    if ($match->status !== 'upcoming') {
        return response()->json([
            'status' => false,
            'message' => 'Contest creation closed for this match'
        ]);
    }

    // ✅ CALCULATIONS (OPTIMIZED)
    $totalCollection = $request->entry_fee * $request->total_slots;

    $platformFee  = round($totalCollection * 0.10, 2); // 10%
    $creatorBonus = round($totalCollection * 0.05, 2); // 5%

    $prizePool = $totalCollection - ($platformFee + $creatorBonus);

    $totalWinners = max(1, floor($request->total_slots / 5));

    $privateCode = strtoupper(substr(md5(uniqid()), 0, 6));

    // ✅ CREATE CONTEST
    $contest = \App\Models\Contest::create([
        'cricket_match_id'     => $match->id,
        'name'                 => $request->name,
        'contest_type'         => 'private',
        'private_code'         => $privateCode,
        'entry_fee'            => $request->entry_fee,
        'total_slots'          => $request->total_slots,
        'filled_slots'         => 0,
        'total_winners'        => $totalWinners,
        'max_team_per_user'    => 1,
        'prize_pool'           => $prizePool,
        'platform_fee'         => $platformFee,
        'creator_bonus'        => $creatorBonus,
        'contest_badge'        => 'Private',
        'status'               => 'upcoming',
        'is_prize_distributed' => 0,
    ]);

    // ✅ AUTO JOIN CREATOR (VERY IMPORTANT 🔥)
    ContestParticipant::create([
        'contest_id'      => $contest->id,
        'user_id'         => $user->id,
        'fantasy_team_id' => null,
        'total_points'    => 0,
        'rank'            => 0,
    ]);

    $contest->increment('filled_slots');

    // ✅ OPTIONAL: CREDIT CREATOR BONUS
    // $user->wallet += $creatorBonus;
    // $user->save();

    // ✅ PRIZE DISTRIBUTION
    $distribution = [35, 20, 10];

    for ($i = 0; $i < $totalWinners; $i++) {

        if ($i < 3) {
            $amount = ($distribution[$i] / 100) * $prizePool;
        } else {
            $remainingPercent = 100 - array_sum($distribution);
            $remainingWinners = $totalWinners - 3;

            if ($remainingWinners > 0) {
                $amount = ($remainingPercent / $remainingWinners / 100) * $prizePool;
            } else {
                $amount = 0;
            }
        }

        \App\Models\ContestPrize::create([
            'contest_id'   => $contest->id,
            'rank_from'    => $i + 1,
            'rank_to'      => $i + 1,
            'prize_amount' => round($amount, 2),
        ]);
    }

    return response()->json([
        'status'  => true,
        'message' => 'Private contest created successfully',
        'data'    => [
            'contest_id'   => $contest->id,
            'private_code' => $privateCode,
            'prize_pool'   => $prizePool,
            'platform_fee' => $platformFee,
            'creator_bonus'=> $creatorBonus,
            'total_winners'=> $totalWinners,
        ],
    ]);
}

   
public function joinPrivateByCode(Request $request)
{
    $request->validate([
        'private_code'    => 'required|string',
        'fantasy_team_id' => 'required|exists:fantasy_teams,id',
    ]);

    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthenticated'
        ]);
    }

   
    $contest = Contest::where('private_code', $request->private_code)
        ->where('contest_type', 'private')
        ->whereIn('status', ['upcoming', 'live'])
        ->first();

    if (!$contest) {
        return response()->json([
            'status' => false,
            'message' => 'Invalid private contest code'
        ]);
    }

   
    if ($contest->filled_slots >= $contest->total_slots) {
        return response()->json([
            'status' => false,
            'message' => 'Contest is full'
        ]);
    }

    
    $alreadyJoined = ContestParticipant::where([
        'contest_id' => $contest->id,
        'user_id'    => $user->id
    ])->exists();

    if ($alreadyJoined) {
        return response()->json([
            'status' => false,
            'message' => 'Already joined this contest'
        ]);
    }

   
    $wallet = Wallet::where('user_id', $user->id)->first();

    if (!$wallet) {
        return response()->json([
            'status' => false,
            'message' => 'Wallet not found'
        ]);
    }

   
    if ($contest->entry_fee > 0 && $wallet->deposit_balance < $contest->entry_fee) {
        return response()->json([
            'status' => false,
            'message' => 'Insufficient balance'
        ]);
    }

    
    \DB::beginTransaction();

    try {

        // 💰 DEDUCT MONEY
        if ($contest->entry_fee > 0) {
            $wallet->deposit_balance -= $contest->entry_fee;
            $wallet->save();

            // 🧾 LOG TRANSACTION
            WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => $contest->entry_fee,
                'wallet_type' => 'deposit',
                'reference_id' => $contest->id,
                'description' => 'Joined private contest #' . $contest->id
            ]);
        }

       
        $participant = ContestParticipant::create([
            'contest_id'      => $contest->id,
            'user_id'         => $user->id,
            'fantasy_team_id' => $request->fantasy_team_id,
            'total_points'    => 0,
            'rank'            => 0
        ]);

      
        $contest->increment('filled_slots');

        \DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Joined contest successfully',
            'data' => [
                'contest_id' => $contest->id,
                'team_id' => $request->fantasy_team_id
            ]
        ]);

    } catch (\Exception $e) {

        \DB::rollback();

        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage() 
        ]);
    }
}


    private function calculateBattingPoints(array $b): float
    {
        $runs      = $b['runs']        ?? 0;
        $balls     = $b['balls']       ?? 0;
        $fours     = $b['fours']       ?? 0;
        $sixes     = $b['sixes']       ?? 0;
        $sr        = $b['strike_rate'] ?? 0;
        $dismissal = $b['dismissal']   ?? '';

        $points = 0;

        // Base points
        $points += $runs * 1;
        $points += $fours * 1;
        $points += $sixes * 2;

        // Milestone bonus
        if ($runs >= 100)     $points += 16;
        elseif ($runs >= 75)  $points += 12;
        elseif ($runs >= 50)  $points += 8;
        elseif ($runs >= 25)  $points += 4;

        // Duck penalty
        if ($runs == 0 && $balls > 0 && $dismissal !== 'not out') {
            $points -= 2;
        }

        // Strike rate bonus/penalty (min 10 balls faced)
        if ($balls >= 10) {
            if ($sr >= 170)     $points += 6;
            elseif ($sr >= 150) $points += 4;
            elseif ($sr >= 130) $points += 2;
            elseif ($sr < 50)   $points -= 6;
            elseif ($sr < 60)   $points -= 4;
            elseif ($sr < 70)   $points -= 2;
        }

        return $points;
    }

    
    private function calculateBowlingPoints(array $b): float
    {
        $wickets = $b['wickets'] ?? 0;
        $maidens = $b['maidens'] ?? 0;
        $runs    = $b['runs']    ?? 0;
        $overs   = $b['overs']   ?? 0;
        $economy = $b['economy'] ?? 0;

        $points = 0;

        // Wicket points
        $points += $wickets * 25;

        // Wicket milestone bonus
        if ($wickets >= 5)      $points += 16;
        elseif ($wickets >= 4)  $points += 8;
        elseif ($wickets >= 3)  $points += 4;

        // Maiden bonus
        $points += $maidens * 12;

        // Economy bonus/penalty (min 2 overs)
        if ($overs >= 2) {
            if ($economy <= 5)      $points += 6;
            elseif ($economy <= 6)  $points += 4;
            elseif ($economy <= 7)  $points += 2;
            elseif ($economy >= 12) $points -= 6;
            elseif ($economy >= 11) $points -= 4;
            elseif ($economy >= 10) $points -= 2;
        }

        return $points;
    }

    
public function calculatePlayerPoints($match_id, CricketApiService $service): array
{
    $matchController = app(\App\Http\Controllers\Api\MatchController::class);
    $scorecardResponse = $matchController->scorecard($match_id, $service);
    $scorecardData = json_decode($scorecardResponse->getContent(), true);

    if (!($scorecardData['status'] ?? false)) {
        return [];
    }

    $data    = $scorecardData['data'] ?? [];
    $batting = $data['batting']       ?? [];
    $bowling = $data['bowling']       ?? [];

    $playerPoints = [];

    foreach ($batting as $inning) {
        foreach ($inning as $b) {

            $name = strtolower(trim($b['name'] ?? ''));
            if (!$name) continue;

            $player = Player::where('cricket_match_id', $match_id)
                ->whereRaw('LOWER(name) = ?', [$name])
                ->first();

            if (!$player) {
                $parts    = explode(' ', $name);
                $lastName = end($parts);
                if (strlen($lastName) > 3) {
                    $player = Player::where('cricket_match_id', $match_id)
                        ->whereRaw('LOWER(name) LIKE ?', ['%' . $lastName . '%'])
                        ->first();
                }
            }

            if (!$player) continue;

            $points = $this->calculateBattingPoints($b);
            $playerPoints[$player->id] = ($playerPoints[$player->id] ?? 0) + $points;
        }
    }

    foreach ($bowling as $inning) {
        foreach ($inning as $b) {

            $name = strtolower(trim($b['name'] ?? ''));
            if (!$name) continue;

            $player = Player::where('cricket_match_id', $match_id)
                ->whereRaw('LOWER(name) = ?', [$name])
                ->first();

            if (!$player) {
                $parts    = explode(' ', $name);
                $lastName = end($parts);
                if (strlen($lastName) > 3) {
                    $player = Player::where('cricket_match_id', $match_id)
                        ->whereRaw('LOWER(name) LIKE ?', ['%' . $lastName . '%'])
                        ->first();
                }
            }

            if (!$player) continue;

            $points = $this->calculateBowlingPoints($b);
            $playerPoints[$player->id] = ($playerPoints[$player->id] ?? 0) + $points;
        }
    }

    foreach ($playerPoints as $playerId => $pts) {

        Player::where('id', $playerId)->update(['points' => $pts]);

        \App\Models\PlayerMatchPoint::updateOrCreate(
            [
                'player_id' => $playerId,
                'match_id'  => $match_id
            ],
            [
                'points' => $pts
            ]
        );
    }

    return $playerPoints;
}

   
    public function calculateContestPoints($contest_id, $match_id, CricketApiService $service)
    {
        // Step 1: Get player points
        $playerPoints = $this->calculatePlayerPoints($match_id, $service);

        if (empty($playerPoints)) {
            return response()->json([
                'status'  => false,
                'message' => 'No player points calculated. Check scorecard data.',
            ]);
        }

        // Step 2: Update each team's total points
        $participants = ContestParticipant::where('contest_id', $contest_id)->get();

        foreach ($participants as $participant) {
            if (!$participant->fantasy_team_id) continue;

            $teamPlayers = \App\Models\FantasyTeamPlayer::with('player')
                ->where('fantasy_team_id', $participant->fantasy_team_id)
                ->get();

            $totalPoints = 0;

            foreach ($teamPlayers as $tp) {
                $playerDbId = $tp->player_id;
                $basePoints = $playerPoints[$playerDbId] ?? 0;

                // Captain 2x, Vice Captain 1.5x
                if ($tp->is_captain)           $basePoints *= 2;
                elseif ($tp->is_vice_captain)  $basePoints *= 1.5;

                $totalPoints += $basePoints;
            }

            $participant->update(['total_points' => round($totalPoints, 2)]);
        }

        // Step 3: Update ranks
        $this->updateRanks($contest_id);

        return response()->json([
            'status'  => true,
            'message' => 'Points calculated and ranks updated',
            'data'    => [
                'contest_id'      => $contest_id,
                'match_id'        => $match_id,
                'players_updated' => count($playerPoints),
                'teams_updated'   => $participants->count(),
            ],
        ]);
    }

  
    private function updateRanks($contest_id): void
    {
        $participants = ContestParticipant::where('contest_id', $contest_id)
            ->orderByDesc('total_points')
            ->get();

        $rank       = 1;
        $prevPoints = null;
        $sameRank   = 1;

        foreach ($participants as $p) {
            if ($prevPoints !== null && $p->total_points == $prevPoints) {
                $currentRank = $sameRank;
            } else {
                $currentRank = $rank;
                $sameRank    = $rank;
            }

            $p->update(['rank' => $currentRank]);
            $prevPoints = $p->total_points;
            $rank++;
        }
    }

   
public function leaderboard($contest_id)
{
    $contest = Contest::with('prizes')->find($contest_id);

    if (!$contest) {
        return response()->json([
            'status' => false,
            'message' => 'Contest not found'
        ]);
    }

    // 🔥 AUTO CALCULATE POINTS
    $service = app(\App\Services\CricketApiService::class);
    $this->calculateContestPoints($contest_id, $contest->cricket_match_id, $service);

    $participants = ContestParticipant::with(['user', 'fantasyTeam'])
        ->where('contest_id', $contest_id)
        ->orderByDesc('total_points')
        ->get();

    $rank = 1;
    $prevPoints = null;

    foreach ($participants as $index => $p) {

        if ($prevPoints !== null && $p->total_points == $prevPoints) {
            $p->rank = $participants[$index - 1]->rank;
        } else {
            $p->rank = $rank;
        }

        $prevPoints = $p->total_points;
        $rank++;

        $p->save();
    }

    // 🔥 FINAL DATA WITH CORRECT PLAYER FORMAT
    $data = $participants->map(function ($p) use ($contest) {

        $winningAmount = 0;

        foreach ($contest->prizes as $prize) {
            if ($p->rank >= $prize->rank_from && $p->rank <= $prize->rank_to) {
                $winningAmount = $prize->prize_amount;
                break;
            }
        }

        // ✅ FIXED PLAYER STRUCTURE (IMPORTANT)
        $players = \App\Models\FantasyTeamPlayer::with('player')
            ->where('fantasy_team_id', $p->fantasy_team_id)
            ->get()
            ->map(function ($tp) {

                $player = $tp->player;

                return [
                    'id' => $tp->player_id, // ✅ REQUIRED
                    'name' => $player->name ?? '',
                    'team_code' => $player->team_code ?? '', // ✅ REQUIRED
                    'role' => $player->role ?? '', // ✅ VERY IMPORTANT (WK/BAT/ALL/BOWL)

                    'is_captain' => (bool) $tp->is_captain,
                    'is_vice_captain' => (bool) $tp->is_vice_captain,
                ];
            });

        return [
            'rank' => $p->rank,
            'user_name' => $p->user->username ?? $p->user->name ?? 'User',
            'team_id' => $p->fantasy_team_id,
            'team_name' => $p->fantasyTeam->team_name ?? 'My Team',
            'points' => (float) $p->total_points,
            'winning_amount' => $winningAmount,

            // 🔥 THIS FIXES YOUR UI
            'players' => $players,
        ];
    });

    return response()->json([
        'status' => true,
        'data' => $data
    ]);
}

  public function myContests()
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'status'  => false,
            'message' => 'Unauthenticated'
        ]);
    }

    $entries = ContestParticipant::where('user_id', $user->id)
        ->with(['contest.match'])
        ->get();

    if ($entries->isEmpty()) {
        return response()->json([
            'status' => true,
            'data'   => ['upcoming' => [], 'live' => [], 'completed' => []]
        ]);
    }

    // Group entries by match_id first
    $matchGroups = [];

    foreach ($entries as $entry) {

        $contest = $entry->contest;
        $match   = $contest->match;

        if (!$contest || !$match) continue;

        $matchId = $match->id;

        // Normalize status
        $status = match(strtolower($match->status ?? '')) {
            'not_started'           => 'upcoming',
            'in_progress', 'live'   => 'live',
            'finished', 'completed' => 'completed',
            default                 => strtolower($match->status ?? 'upcoming')
        };

        // Format match time IST
        $startTime = '';
        if ($match->match_start_time) {
            $startTime = \Carbon\Carbon::parse($match->match_start_time)
                ->setTimezone('Asia/Kolkata')
                ->format('Y-m-d H:i:s');
        }

        // Status text
        $statusText = match($status) {
            'upcoming'  => $match->match_start_time
                ? 'Starts in ' . now()->diffForHumans(
                    \Carbon\Carbon::parse($match->match_start_time)->setTimezone('Asia/Kolkata'),
                    true
                  )
                : 'Upcoming',
            'live'      => 'Live',
            'completed' => 'Completed',
            default     => ''
        };

        // Init match group once
        if (!isset($matchGroups[$matchId])) {
            $matchGroups[$matchId] = [
                'match_id'    => $match->id,
                'match_name'  => ($match->team1_code ?? $match->team_1 ?? '') . ' vs ' . ($match->team2_code ?? $match->team_2 ?? ''),
                'series_name' => $match->series_name ?? '',
                'status'      => $statusText,
                'start_time'  => $startTime,
                '_status_key' => $status,  // internal, removed before output
                'contests'    => [],
            ];
        }

        // Contest row
        $totalSlots      = $contest->total_slots  ?? 0;
        $filledSlots     = $contest->filled_slots ?? 0;
        $spotsLeft       = max($totalSlots - $filledSlots, 0);
        $progressPercent = $totalSlots > 0
            ? round(($filledSlots / $totalSlots) * 100)
            : 0;

        $matchGroups[$matchId]['contests'][] = [
            'contest_id'       => $contest->id,
            'contest_name'     => $contest->name,
            'prize_pool'       => (float) $contest->prize_pool,
            'entry_fee'        => (float) $contest->entry_fee,
            'total_spots'      => $totalSlots,
            'spots_left'       => $spotsLeft,
            'progress_percent' => $progressPercent,
            'total_winners'    => $contest->total_winners ?? 0,
            'tag'              => $contest->contest_badge ?? '',
            'show_bike'        => ($contest->contest_badge ?? '') === 'Free Bike',

            // User's performance in this contest
            'team_id'          => $entry->fantasy_team_id,
            'points'           => (float) ($entry->total_points ?? 0),
            'rank'             => $entry->rank    ?? 0,
            'winning_amount'   => (float) ($entry->winning_amount ?? 0),
        ];
    }

    // Group matches by status
    $grouped = ['upcoming' => [], 'live' => [], 'completed' => []];

    foreach ($matchGroups as $match) {
        $key = $match['_status_key'];
        unset($match['_status_key']); // remove internal key before output
        $grouped[$key][] = $match;
    }

    return response()->json([
        'status' => true,
        'data'   => $grouped
    ]);
}

public function myContestsByMatch($match_id)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthenticated'
        ]);
    }

    $entries = ContestParticipant::where('user_id', $user->id)
    ->whereHas('contest', function ($q) use ($match_id) {
        $q->where('cricket_match_id', $match_id)
          ->whereRaw('LOWER(contest_type) = ?', ['public']);
    })
    ->with(['contest.match'])
    ->get();

    if ($entries->isEmpty()) {
        return response()->json([
            'status' => true,
            'data' => [
                'upcoming' => [],
                'live' => [],
                'completed' => []
            ]
        ]);
    }

    // ✅ SAFE API CALL
    $teamA = '';
    $teamB = '';
    $matchTime = '';

    try {
        $apiService = app(\App\Services\CricketApiService::class);
        $apiData = $apiService->getMatchInfo($match_id);

        if (!empty($apiData) && isset($apiData['data'])) {

            $matchData = $apiData['data'];

            // ✅ SAFE ACCESS
            $teamA = $matchData['localteam']['name'] ?? '';
            $teamB = $matchData['visitorteam']['name'] ?? '';

            $matchTimeRaw = $matchData['starting_at'] ?? null;

            if ($matchTimeRaw) {
                $matchTime = \Carbon\Carbon::parse($matchTimeRaw)->format('h:i A');
            }
        }

    } catch (\Exception $e) {
        // ❌ FAIL SAFE (no crash)
        $teamA = '';
        $teamB = '';
        $matchTime = '';
    }

    $grouped = [
        'upcoming' => [],
        'live' => [],
        'completed' => []
    ];

    foreach ($entries as $entry) {

        $contest = $entry->contest;
        $match   = $contest->match;

        // ✅ STATUS TEXT
        $statusText = match ($match->status) {
            'upcoming' => 'Match starts soon',
            'live' => 'Live now',
            'completed' => 'Completed',
            default => ''
        };

        // ✅ CONTEST CALCULATION
        $totalSlots = $contest->total_slots ?? 0;

        $filledSlots = $contest->filled_slots
            ?? ContestParticipant::where('contest_id', $contest->id)->count();

        $spotsLeft = max($totalSlots - $filledSlots, 0);

        $progressPercent = $totalSlots > 0
            ? round(($filledSlots / $totalSlots) * 100)
            : 0;

        $item = [
           
    // 🔹 CONTEST BASIC
    'contest_id'   => $contest->id,
    'contest_name' => $contest->name,              // ✅ ADD THIS
    'first_prize'  => $contest->first_prize ?? '', // ✅ ADD THIS
            'match_id'   => $match->id,
            'match_name' => ($teamA && $teamB) ? "$teamA vs $teamB" : '',
            'team_a'     => $teamA,
            'team_b'     => $teamB,
            'series_name'=> $match->series_name ?? '',
            'match_time' => $matchTime,
            'status'     => $match->status,
            'status_text'=> $statusText,

            // 🔹 CONTEST
            'entry_fee'  => $contest->entry_fee,
            'prize_pool' => $contest->prize_pool,
            'team_id'    => $entry->fantasy_team_id,
            'points'     => (float) ($entry->total_points ?? 0),
            'rank'       => $entry->rank ?? 0,

            // 🔥 UI FIELDS
            'spots_left'       => $spotsLeft,
            'total_spots'      => $totalSlots,
            'total_winners'    => $contest->total_winners ?? 0,
            'progress_percent' => $progressPercent,
            'tag'              => $contest->contest_badge ?? 'Joined',
            'show_bike'        => ($contest->contest_badge ?? '') === 'Free Bike',
        ];

        $grouped[$match->status][] = $item;
    }

    return response()->json([
        'status' => true,
        'data' => $grouped
    ]);
}

public function winnings($contest_id)
{
    $prizes = \App\Models\ContestPrize::where('contest_id', $contest_id)
        ->orderBy('rank_from')
        ->get();

    if ($prizes->isEmpty()) {
        return response()->json([
            'status' => false,
            'message' => 'Winnings info not available'
        ]);
    }

    $data = $prizes->map(function ($p) {

        // Rank format (1 or 2-10)
        $rank = $p->rank_from == $p->rank_to
            ? (string) $p->rank_from
            : $p->rank_from . '-' . $p->rank_to;

        return [
            'rank'         => $rank,
            'prize_amount' => (float) $p->prize_amount,
            'extra_prize'  => $p->extra_prize,
        ];
    });

    return response()->json([
        'status' => true,
        'data'   => $data
    ]);
}

    public function distributeWinnings($contest_id)
    {
        $contest = Contest::with('prizes')->find($contest_id);

        if (!$contest) {
            return response()->json(['status' => false, 'message' => 'Contest not found']);
        }

        if ($contest->is_prize_distributed) {
            return response()->json(['status' => false, 'message' => 'Prizes already distributed']);
        }

        $participants = ContestParticipant::where('contest_id', $contest_id)
            ->orderBy('rank')
            ->get();

        DB::beginTransaction();

        try {
            $winnersCount = 0;

            foreach ($participants as $p) {
                if (!$p->rank) continue;

                // Find matching prize for rank
                $prize = $contest->prizes
                    ->where('rank_from', '<=', $p->rank)
                    ->where('rank_to',   '>=', $p->rank)
                    ->first();

                if (!$prize) continue;

                $winAmount = $prize->prize_amount;

                $wallet = Wallet::where('user_id', $p->user_id)->first();

                if ($wallet) {
                    $wallet->winning_balance += $winAmount;
                    $wallet->save();

                    WalletTransaction::create([
                        'user_id'      => $p->user_id,
                        'amount'       => $winAmount,
                        'type'         => 'winning_credit',
                        'wallet_type'  => 'winning',
                        'reference_id' => $contest_id,
                        'description'  => 'Won ₹' . $winAmount . ' - Contest #' . $contest_id . ' Rank #' . $p->rank,
                    ]);
                }

                $p->update(['winning_amount' => $winAmount]);
                $winnersCount++;
            }

            $contest->update([
                'is_prize_distributed' => 1,
                'status'               => 'completed',
            ]);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Prizes distributed successfully',
                'data'    => [
                    'contest_id' => $contest_id,
                    'prize_pool' => $contest->prize_pool,
                    'winners'    => $winnersCount,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Distribution failed',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }


    public function testPoints($match_id, CricketApiService $service)
    {
        $points = $this->calculatePlayerPoints($match_id, $service);

        $players = Player::whereIn('id', array_keys($points))
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($points as $playerId => $pts) {
            $result[] = [
                'player_id'   => $playerId,
                'name'        => $players[$playerId]->name ?? 'Unknown',
                'team'        => $players[$playerId]->team_code ?? '',
                'points'      => $pts,
            ];
        }

        usort($result, fn($a, $b) => $b['points'] <=> $a['points']);

        return response()->json([
            'status' => true,
            'total_players_calculated' => count($result),
            'data'   => $result,
        ]);
    }

  public function myMatches()
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthenticated'
        ]);
    }

    // 🔥 GET USER ENTRIES WITH RELATIONS
    $entries = \App\Models\ContestParticipant::where('user_id', $user->id)
        ->with(['contest.match'])
        ->get();

    $grouped = [
        'upcoming' => [],
        'live' => [],
        'completed' => []
    ];

    $matches = [];

    foreach ($entries as $entry) {

        $contest = $entry->contest;
        $match   = $contest->match;

        if (!$contest || !$match) continue;

        $matchId = $match->id;

        // 🕒 TIME CONVERT (IST)
        $matchTimeIST = \Carbon\Carbon::parse($match->match_start_time)
            ->setTimezone('Asia/Kolkata');

        // 🕒 STATUS TEXT
        $statusText = match ($match->status) {
            'upcoming' => 'Starts in ' . now()->diffForHumans($matchTimeIST, true),
            'live' => 'Live',
            'completed' => 'Completed',
            default => ''
        };

        // 📊 CALCULATIONS
        $totalSlots  = $contest->total_slots ?? 0;
        $filledSlots = $contest->filled_slots ?? 0;

        $spotsLeft = max($totalSlots - $filledSlots, 0);

        $progressPercent = $totalSlots > 0
            ? round(($filledSlots / $totalSlots) * 100)
            : 0;

        // 👤 USER TEAMS COUNT
        $teamsJoined = \App\Models\ContestParticipant::where('contest_id', $contest->id)
            ->where('user_id', $user->id)
            ->count();

        // 🧱 CREATE MATCH GROUP
        if (!isset($matches[$matchId])) {

            $matches[$matchId] = [
                'match_id' => $match->id,
                'match_name' => ($match->team_1 ?? '') . ' vs ' . ($match->team_2 ?? ''),
                'series_name' => $match->series_name ?? '',
                'status' => $statusText,
                'start_time' => $matchTimeIST->format('h:i A'),
                'contests' => []
            ];
        }

        // 🎯 ADD CONTEST
        $matches[$matchId]['contests'][] = [
            'contest_id' => $contest->id,
            'contest_name' => $contest->name,
            'prize_pool' => (float) $contest->prize_pool,
            'entry_fee' => (float) $contest->entry_fee,
            'total_spots' => $totalSlots,
            'spots_left' => $spotsLeft,
            'progress_percent' => $progressPercent,
            'total_winners' => $contest->total_winners ?? 0,
            'teams_joined' => $teamsJoined,
            'tag' => $contest->contest_type === 'private'
                ? 'Play for Pride'
                : ($contest->contest_badge ?? ''),
            'show_bike' => ($contest->contest_badge ?? '') === 'Free Bike',
        ];
    }

    // 🔥 GROUP BY STATUS (SAFE)
    foreach ($matches as $match) {

        if (str_contains($match['status'], 'Live')) {
            $grouped['live'][] = $match;
        } elseif (str_contains($match['status'], 'Completed')) {
            $grouped['completed'][] = $match;
        } else {
            $grouped['upcoming'][] = $match;
        }
    }

    return response()->json([
        'status' => true,
        'data' => $grouped
    ]);
}
}