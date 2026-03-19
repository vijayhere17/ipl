<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = [
    'api_player_id',
    'name',
    'team_name',
    'role',
    'credit'
];
}
