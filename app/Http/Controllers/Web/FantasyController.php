<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FantasyController extends Controller
{
    private $api = 'http://127.0.0.1:8000/api/v1';

    public function matches()
    {
        $res = Http::get($this->api.'/matches');
        $data = $res->json()['data'];

        $matches = array_merge(
            $data['live_matches'],
            $data['upcoming_matches']
        );

        return view('matches', compact('matches'));
    }

public function players($match_id)
{
    $res = Http::get($this->api."/match/$match_id/players");

    $json = $res->json();

    if (!$json || !isset($json['data'])) {
        return dd($json);
    }

    $team1 = $json['data']['team1']['players'] ?? [];
    $team2 = $json['data']['team2']['players'] ?? [];

    // Merge both teams
    $players = array_merge($team1, $team2);

    return view('players', compact('players', 'match_id'));
}

    public function createTeam(Request $request, $match_id)
    {
        $players = $request->players;
        return view('create-team', compact('players', 'match_id'));
    }

    public function storeTeam(Request $request)
    {
        $response = Http::post($this->api.'/create-team', $request->all());

        return redirect('/my-teams/'.$request->cricket_match_id);
    }

public function myTeams($match_id)
{
    $res = Http::timeout(10)->get($this->api."/my-teams/$match_id");

    $json = $res->json();

    if (!$json || !isset($json['data'])) {
        return "API ERROR: " . $res->body();
    }

    $teams = $json['data'];

    return view('my-teams', compact('teams', 'match_id'));
}

    public function preview($team_id)
    {
        $res = Http::get($this->api."/team-preview/$team_id");
        $team = $res->json()['data'];

        return view('preview', compact('team'));
    }

    public function leaderboard($contest_id)
    {
        $res = Http::get($this->api."/leaderboard/$contest_id");
        $data = $res->json()['data'];

        return view('leaderboard', compact('data'));
    }
}