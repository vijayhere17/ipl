<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\ContestController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\Api\FantasyTeamController;
use App\Services\CricketApiService;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Prefix: /api/v1
| This file contains all public and authenticated APIs for the fantasy app
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | PUBLIC APIs (No authentication required)
    |--------------------------------------------------------------------------
    */

      // Create fantasy team
        Route::post('/create-team',[FantasyTeamController::class,'createTeam']);

        // Get user's teams for a specific match
        Route::get('/my-teams/{match_id}', [FantasyTeamController::class,'myTeams']);

        // Team preview (players + captain/vice captain)
        Route::get('/team/{team_id}', [FantasyTeamController::class,'teamPreview']);

    // Home screen APIs
    Route::get('/home/matches', [MatchController::class,'homeMatches']);
    Route::get('/home/upcoming-matches', [MatchController::class,'upcomingMatches']);
    Route::get('/home/contests', [MatchController::class,'homeContests']);

    // Match APIs
    Route::get('/matches', [MatchController::class, 'index']);
    Route::get('/matches/{id}', [MatchController::class, 'show']);

    Route::get('/match/{id}/info', [MatchController::class, 'matchInfo']);
    Route::get('/match/{id}/details', [MatchController::class,'matchDetails']);
   Route::get('/match/live', [MatchController::class, 'live']); // auto
Route::get('/match/{id}/live', [MatchController::class, 'live']); // manual

    // Match statistics
    Route::get('/match/{id}/score', [MatchController::class, 'score']);
    Route::get('/match/{id}/scorecard', [MatchController::class, 'scorecard']);

    // Match squads and players
    Route::get('/match/{id}/squads', [MatchController::class, 'squads']);
    Route::get('/match/{id}/players', [MatchController::class, 'players']);

    // Contest list for a match
    Route::get('/contests/{match_id}', [ContestController::class, 'getMatchContests']);

    // Contest leaderboard
    Route::get('/contest/{contest_id}/leaderboard', [ContestController::class, 'leaderboard']);

    /*
    |--------------------------------------------------------------------------
    | Authentication APIs
    |--------------------------------------------------------------------------
    */

    Route::post('/send-email-otp', [AuthController::class, 'sendOtp']);
    Route::post('/verify-email-otp', [AuthController::class, 'verifyOtp']);


    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATED APIs (Requires Sanctum Token)
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | User Profile
        |--------------------------------------------------------------------------
        */

        Route::get('/user/profile', [AuthController::class, 'profile']);
        Route::post('/user/update-profile', [AuthController::class, 'updateProfile']);


        /*
        |--------------------------------------------------------------------------
        | Fantasy Team APIs
        |--------------------------------------------------------------------------
        */

      


        /*
        |--------------------------------------------------------------------------
        | Contest APIs
        |--------------------------------------------------------------------------
        */

        // Join public contest
        Route::post('/contest/join', [ContestController::class, 'join']);

        // Create private contest
        Route::post('/contest/private/create', [ContestController::class, 'createPrivateContest']);

        // Join private contest using code
        Route::post('/contest/private/join', [ContestController::class, 'joinPrivateByCode']);


        /*
        |--------------------------------------------------------------------------
        | Withdrawal APIs
        |--------------------------------------------------------------------------
        */

        // Request withdrawal
        Route::post('/withdraw/request', [WithdrawalController::class, 'requestWithdrawal']);

    });

});


/*
|--------------------------------------------------------------------------
| Debug / Test Route
|--------------------------------------------------------------------------
| Used to test CricAPI integration
|--------------------------------------------------------------------------
*/

Route::get('/test-cricapi', function (CricketApiService $service) {
    return $service->getCurrentMatches();
});