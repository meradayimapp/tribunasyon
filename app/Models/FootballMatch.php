<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FootballMatch extends Model
{
    protected $fillable = [
        'competition_id',
        'home_football_team_id',
        'away_football_team_id',
        'provider',
        'provider_match_id',
        'season',
        'week',
        'round',
        'kickoff_at',
        'status',
        'state',
        'status_display',
        'is_live',
        'home_score',
        'away_score',
        'home_halftime_score',
        'away_halftime_score',
        'home_penalty_score',
        'away_penalty_score',
        'tv_broadcast',
        'last_synced_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'kickoff_at' => UtcDateTime::class,
            'is_live' => 'boolean',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'home_halftime_score' => 'integer',
            'away_halftime_score' => 'integer',
            'home_penalty_score' => 'integer',
            'away_penalty_score' => 'integer',
            'tv_broadcast' => 'boolean',
            'last_synced_at' => UtcDateTime::class,
            'meta' => 'array',
        ];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(FootballCompetition::class, 'competition_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(FootballTeam::class, 'home_football_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(FootballTeam::class, 'away_football_team_id');
    }

    public function statusLabel(): string
    {
        if ($this->is_live) {
            return $this->status_display ?: 'CANLI';
        }

        return $this->status_display ?: match ($this->status) {
            'scheduled', 'not_started' => 'Başlamadı',
            'finished' => 'Bitti',
            'postponed' => 'Ertelendi',
            'cancelled', 'canceled' => 'İptal',
            default => $this->status,
        };
    }
}
