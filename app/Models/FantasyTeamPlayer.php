<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyTeamPlayer extends Model
{
    protected $fillable = [
        'fantasy_team_id',
        'player_id',
        'is_captain',
        'is_vice_captain'
    ];

    public function team()
    {
        return $this->belongsTo(FantasyTeam::class,'fantasy_team_id');
    }

    public function player()
    {
        return $this->belongsTo(Player::class,'player_id');
    }
}