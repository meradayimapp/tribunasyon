<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
        'lineups',
        'lineup_is_projected',
        'lineup_synced_at',
        'match_stats',
        'venue_name',
        'referee_name',
        'tv_channels',
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
            'lineups' => 'array',
            'lineup_is_projected' => 'boolean',
            'lineup_synced_at' => UtcDateTime::class,
            'match_stats' => 'array',
            'tv_channels' => 'array',
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

    public function scopeIndexable(Builder $query): Builder
    {
        $activeFootballTeam = fn (Builder $team): Builder => $team
            ->where('is_active', true)
            ->where(fn (Builder $linked): Builder => $linked
                ->whereNull('team_id')
                ->orWhereHas('team', fn (Builder $social): Builder => $social->active()));

        return $query
            ->whereHas('competition', fn (Builder $competition): Builder => $competition->active())
            ->whereHas('homeTeam', $activeFootballTeam)
            ->whereHas('awayTeam', $activeFootballTeam);
    }

    public function isIndexable(): bool
    {
        $linkedTeamIsActive = static fn ($footballTeam): bool => $footballTeam->is_active
            && ($footballTeam->team_id === null || (
                $footballTeam->team !== null
                && ! $footballTeam->team->trashed()
                && $footballTeam->team->status->value === 'active'
            ));

        return $this->competition->is_active
            && $linkedTeamIsActive($this->homeTeam)
            && $linkedTeamIsActive($this->awayTeam);
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

    public function displayMinute(): ?int
    {
        if ($this->live_minute !== null) {
            return $this->live_minute;
        }

        if ($this->is_live && preg_match('/^(\d{1,3})[\'′]?$/u', trim((string) $this->status_display), $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public function statePayload(): array
    {
        return [
            'status' => $this->status,
            'is_live' => $this->is_live,
            'is_finished' => $this->isFinished(),
            'score' => ['home' => $this->home_score, 'away' => $this->away_score],
            'minute' => $this->displayMinute(),
            'status_display' => $this->stateStatusLabel(),
            'events' => $this->live_events ?? [],
            'updated_at' => ($this->live_details_synced_at ?? $this->last_synced_at)?->toIso8601String(),
        ];
    }

    public function stateStatusLabel(): string
    {
        if ($this->is_live && $this->displayMinute() !== null && preg_match('/^\d{1,3}[\'′]?$/u', (string) $this->status_display)) {
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
