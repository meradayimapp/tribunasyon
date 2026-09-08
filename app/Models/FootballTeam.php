<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FootballTeam extends Model
{
    protected $fillable = [
        'provider',
        'provider_team_id',
        'provider_name',
        'display_name',
        'provider_logo_url',
        'country',
        'is_active',
        'last_synced_at',
        'team_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => UtcDateTime::class,
        ];
    }

    protected function resolvedName(): Attribute
    {
        return Attribute::get(fn (): string => $this->team?->name ?: $this->display_name ?: $this->provider_name);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function homeMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'home_football_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'away_football_team_id');
    }

    public function logoUrl(): ?string
    {
        return $this->team?->logoUrl() ?: $this->provider_logo_url;
    }
}
