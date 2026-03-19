<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CricketMatch extends Model
{
    protected $table = 'cricket_matches';

    protected $fillable = [
        'api_match_id',
        'series_name',
        'team_1',
        'team_2',
        'match_start_time',
        'status',

        // score fields
        'team1_score',
        'team1_wicket',
        'team1_over',
        'team2_score',
        'team2_wicket',
        'team2_over',
    ];

    protected $casts = [
        'match_start_time' => 'datetime',
    ];
}