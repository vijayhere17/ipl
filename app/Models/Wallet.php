<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'deposit_balance',
        'winning_balance',
        'bonus_balance'
    ];
}