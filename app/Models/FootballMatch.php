<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FootballMatch extends Model
{
    public const DISPLAY_TIMEZONE = 'Europe/Istanbul';

    public const TERMINAL_STATUSES = ['finished', 'cancelled', 'canceled', 'postponed', 'abandoned'];

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
        'live_minute',
        'live_events',
        'live_details_synced_at',
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
            'live_minute' => 'integer',
            'live_events' => 'array',
            'live_details_synced_at' => UtcDateTime::class,
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

    public function chatMessages(): HasMany
    {
        return $this->hasMany(FootballMatchChatMessage::class);
    }

    public function kickoffInDisplayTimezone(): CarbonImmutable
    {
        return $this->kickoff_at->setTimezone(self::DISPLAY_TIMEZONE);
    }

    public function kickoffTime(): string
    {
        return $this->kickoffInDisplayTimezone()->format('H:i');
    }

    public function isFinished(): bool
    {
        return in_array(strtolower($this->status), self::TERMINAL_STATUSES, true);
    }

    public function statePayload(): array
    {
        return [
            'status' => $this->status,
            'is_live' => $this->is_live,
            'is_finished' => $this->isFinished(),
            'score' => ['home' => $this->home_score, 'away' => $this->away_score],
            'minute' => $this->live_minute,
            'status_display' => $this->stateStatusLabel(),
            'updated_at' => ($this->live_details_synced_at ?? $this->last_synced_at)?->toIso8601String(),
        ];
    }

    public function stateStatusLabel(): string
    {
        if ($this->is_live && $this->live_minute !== null && preg_match('/^\d{1,3}[\'′]?$/u', (string) $this->status_display)) {
            return 'CANLI';
        }

        return $this->statusLabel();
    }

    public function statusLabel(): string
    {
        if ($this->is_live) {
            return $this->status_display ?: 'CANLI';
        }

        $scheduled = in_array($this->status, ['scheduled', 'not_started'], true);

        if ($this->status_display && ! ($scheduled && preg_match('/^\d{2}:\d{2}$/', $this->status_display))) {
            return $this->status_display;
        }

        return match ($this->status) {
            'scheduled', 'not_started' => 'Başlamadı',
            'finished' => 'Bitti',
            'postponed' => 'Ertelendi',
            'cancelled', 'canceled' => 'İptal',
            default => $this->status,
        };
    }
}
