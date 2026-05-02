<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contest extends Model
{
    protected $table = 'contests';

    protected $fillable = [
    'cricket_match_id',
    'name',
    'contest_type',
    'created_by', // 🔥 IMPORTANT ADD
    'private_code',
    'entry_fee',
    'total_slots',
    'filled_slots',
    'prize_pool',
    'platform_fee',

    'contest_badge',
    'first_prize',
    'max_team_per_user',
    'total_winners',
    'is_guaranteed',

    'status',
    'is_prize_distributed'
];

    protected $casts = [
        'entry_fee' => 'float',
        'platform_fee' => 'float',
        'prize_pool' => 'float',
        'is_prize_distributed' => 'boolean',
        'is_guaranteed' => 'boolean'
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function match()
    {
        return $this->belongsTo(CricketMatch::class, 'cricket_match_id');
    }

    public function prizes()
    {
        return $this->hasMany(ContestPrize::class);
    }
}