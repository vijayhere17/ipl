<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContestParticipant extends Model
{
    protected $fillable = [
    'contest_id',
    'user_id',
    'fantasy_team_id',
    'total_points',
    'rank'
];

public function user()
{
    return $this->belongsTo(\App\Models\User::class);
}

public function fantasyTeam()
{
    return $this->belongsTo(\App\Models\FantasyTeam::class);
}
}
