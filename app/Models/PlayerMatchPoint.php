<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerMatchPoint extends Model
{
    protected $table = 'player_match_points';

    protected $fillable = [
        'player_id',
        'match_id',
        'points',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id');
    }
}