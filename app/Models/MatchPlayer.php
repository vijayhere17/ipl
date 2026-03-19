<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchPlayer extends Model
{
    protected $fillable = [
    'cricket_match_id',
    'player_id',
    'credit',
    'is_playing'
];
}
