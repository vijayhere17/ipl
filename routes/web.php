<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\FantasyController;

Route::get('/match-test', function () {
    return view('match-test');
});


Route::get('/', [FantasyController::class, 'matches']);
Route::get('/players/{match_id}', [FantasyController::class, 'players']);
Route::get('/create-team/{match_id}', [FantasyController::class, 'createTeam']);
Route::post('/store-team', [FantasyController::class, 'storeTeam']);

Route::get('/my-teams/{match_id}', [FantasyController::class, 'myTeams']);
Route::get('/team-preview/{team_id}', [FantasyController::class, 'preview']);
Route::get('/leaderboard/{contest_id}', [FantasyController::class, 'leaderboard']);

Route::get('/match/{id}/live-view', function ($id) {
    return view('live-match', compact('id'));
});