<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyTeam extends Model
{
    protected $fillable = [
        'user_id',
        'cricket_match_id',
        'team_name',
        'total_points'
    ];

    public function players()
    {
        return $this->hasMany(FantasyTeamPlayer::class);
    }

    public function match()
    {
        return $this->belongsTo(CricketMatch::class,'cricket_match_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}