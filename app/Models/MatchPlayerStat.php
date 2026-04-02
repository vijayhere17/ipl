<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchPlayerStat extends Model
{
    protected $fillable = [
    'player_id',
    'cricket_match_id', // ✅ ADD THIS
    'runs',
    'wickets',
    'catches',
    'points'
];
}
