<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'wallet_type',
        'reference_id',
        'description'
    ];
}