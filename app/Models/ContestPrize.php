<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContestPrize extends Model
{
    protected $fillable = [
        'contest_id',
        'rank_from',
        'rank_to',
        'prize_amount',
        'extra_prize'
    ];

    public function contest()
    {
        return $this->belongsTo(Contest::class);
    }
}