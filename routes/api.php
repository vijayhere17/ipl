<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\ContestController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\Api\FantasyTeamController;
use App\Services\CricketApiService;
use App\Http\Controllers\Api\WalletController;

Route::prefix('v1')->group(function () {

   

    // Manual trigger routes (admin use)
Route::get('/admin/sync-matches',    function () {
    app(MatchController::class)
        ->index(app(CricketApiService::class));
    return response()->json(['status' => true, 'message' => 'Matches synced']);
});

Route::get('/admin/update-points',   function () {
    \App\Models\CricketMatch::where('status', 'live')
        ->each(function ($match) {
            \App\Models\Contest::where('cricket_match_id', $match->id)
                ->where('is_prize_distributed', 0)
                ->each(function ($contest) use ($match) {
                    app(ContestController::class)
                        ->calculateContestPoints(
                            $contest->id,
                            $match->id,
                            app(CricketApiService::class)
                        );
                });
        });
    return response()->json(['status' => true, 'message' => 'Points updated']);
});

Route::get('/admin/distribute-prizes', function () {
    \App\Models\CricketMatch::where('status', 'completed')
        ->each(function ($match) {
            \App\Models\Contest::where('cricket_match_id', $match->id)
                ->where('is_prize_distributed', 0)
                ->each(function ($contest) {
                    app(ContestController::class)
                        ->distributeWinnings($contest->id);
                });
        });
    return response()->json(['status' => true, 'message' => 'Prizes distributed']);
});

    // Match routes
    Route::get('/matches',                      [MatchController::class, 'index']);
    Route::get('/matches/{id}',                 [MatchController::class, 'show']);
    Route::get('/home/matches',                 [MatchController::class, 'homeMatches']);
    Route::get('/home/upcoming-matches',        [MatchController::class, 'upcomingMatches']);
    Route::get('/home/contests',                [MatchController::class, 'homeContests']);
    Route::get('/match/live',                   [MatchController::class, 'live']);
    Route::get('/match/{id}',                   [MatchController::class, 'matchDetails']);
    Route::get('/match/{id}/live',              [MatchController::class, 'live']);
    Route::get('/match/{id}/info',              [MatchController::class, 'matchInfo']);
    Route::get('/match/{id}/score',             [MatchController::class, 'score']);
    Route::get('/match/{id}/scorecard',         [MatchController::class, 'scorecard']);
    Route::get('/match/{id}/squads',            [MatchController::class, 'squads']);
    Route::get('/match/{id}/players',           [MatchController::class, 'players']);
    Route::get('/match/{id}/players/refresh',   [MatchController::class, 'refreshPlayers']);

   

    // Contest public routes
    Route::get('/contests/{match_id}',                          [ContestController::class, 'getMatchContests']);
    Route::get('/contest/{contest_id}/leaderboard',             [ContestController::class, 'leaderboard']);

    // Auth routes
    Route::post('/send-email-otp',    [AuthController::class, 'sendOtp']);
    Route::post('/verify-email-otp',  [AuthController::class, 'verifyOtp']);

    // ================================================
    // ADMIN / DEBUG ROUTES (remove in production)
    // ================================================
    Route::get('/match/{id}/sync-players',                          [MatchController::class, 'syncPlayersData']);
    Route::get('/match/{id}/debug',                                 [MatchController::class, 'debugScorecard']);
    Route::get('/match/{id}/players-list',                          [MatchController::class, 'playersList']);
    Route::get('/test-points/{match_id}',                           [ContestController::class, 'testPoints']);
    Route::get('/contest/{contest_id}/calculate/{match_id}',        [ContestController::class, 'calculateContestPoints']);
    Route::get('/contest/{contest_id}/distribute',                  [ContestController::class, 'distributeWinnings']);
    Route::get('/contest/{contest_id}/winnings', [ContestController::class, 'winnings']);

    // ================================================
    // AUTHENTICATED ROUTES
    // ================================================
    Route::middleware('auth:sanctum')->group(function () {

     Route::get('/my-matches', [ContestController::class, 'myMatches']);

        // User
        Route::get('/user/profile',           [AuthController::class, 'profile']);
        Route::post('/user/update-profile',   [AuthController::class, 'updateProfile']);
        Route::get('/wallet',                 [AuthController::class, 'wallet']);

        // Fantasy Teams
        Route::post('/create-team',           [FantasyTeamController::class, 'createTeam']);
        Route::get('/my-teams/{match_id}',    [FantasyTeamController::class, 'myTeams']);
        Route::get('/team/{team_id}',         [FantasyTeamController::class, 'teamPreview']);

        Route::post('/update-team/{team_id}', [FantasyTeamController::class, 'updateTeam']);

        // Contests
        Route::post('/contest/join',                [ContestController::class, 'join']);
        Route::post('/contest/private/create',      [ContestController::class, 'createPrivateContest']);
        Route::post('/contest/private/join',        [ContestController::class, 'joinPrivateByCode']);
        Route::get('/my-contests', [ContestController::class, 'myContests']);
        Route::get('/my-contests/{match_id}', [ContestController::class, 'myContestsByMatch']);

        Route::get('/private-contests/my', [ContestController::class, 'myPrivateContests']);

        Route::get('/wallet', [WalletController::class, 'wallet']);
        Route::post('/wallet/create-address', [WalletController::class, 'createWalletAddress']);
        Route::post('/wallet/add-money', [WalletController::class, 'addMoney']);
        Route::post('/wallet/transfer', [WalletController::class, 'transfer']);
        Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);

        // Wallet
        Route::post('/withdraw/request',      [WithdrawalController::class, 'requestWithdrawal']);
    });
});

Route::get('/test-cricapi', function (CricketApiService $service) {
    return $service->getCurrentMatches();
});


Route::get('/test-points/{match_id}', function ($match_id) {
    app(\App\Services\FantasyPointService::class)->updateMatchPoints($match_id);
    return "Points Updated";
});