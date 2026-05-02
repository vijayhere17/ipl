<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $table = 'players';

    protected $fillable = [
        'api_player_id',
        'name',
        'team_name',
        'team_code',
        'role',
        'credit',
        'image',
        'points',
        'selection_percentage',
        'is_playing',
        'is_captain',
        'is_vice_captain',
        'is_wk',
        'substitution',
        'played_last_match',
        'last_match_points',
        'cricket_match_id',
        'season_id',
    ];

    protected $casts = [
        'credit'               => 'float',
        'points'               => 'integer',
        'selection_percentage' => 'integer',
        'last_match_points'    => 'integer',
        'is_playing'           => 'boolean',
        'is_captain'           => 'boolean',
        'is_vice_captain'      => 'boolean',
        'is_wk'                => 'boolean',
        'substitution'         => 'boolean',
        'played_last_match'    => 'boolean',
    ];

    // ================================================
    // RELATIONSHIPS
    // ================================================

    public function match()
    {
        return $this->belongsTo(CricketMatch::class, 'cricket_match_id');
    }

    // ================================================
    // SCOPES
    // ================================================

    public function scopeForMatch($query, $matchId)
    {
        return $query->where('cricket_match_id', $matchId);
    }

    public function scopePlaying($query)
    {
        return $query->where('is_playing', 1);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeByTeam($query, $teamCode)
    {
        return $query->where('team_code', $teamCode);
    }
}