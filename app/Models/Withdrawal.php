<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'wallet_address',
        'network',
        'status',
        'admin_note',
        'processed_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}