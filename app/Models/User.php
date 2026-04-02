<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

   protected $fillable = [
 'username',
 'name',
 'email',
 'mobile',
 'password',
 'referral_code',
 'referred_by',
 'referral_reward_given',
 'bonus_balance'
];

public function wallet()
{
    return $this->hasOne(\App\Models\Wallet::class, 'user_id');
}
}