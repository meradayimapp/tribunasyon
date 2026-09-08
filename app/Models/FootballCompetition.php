<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FootballCompetition extends Model
{
    protected $fillable = [
        'provider',
        'provider_league_id',
        'name',
        'display_name',
        'slug',
        'country',
        'provider_logo_url',
        'logo_path',
        'current_season',
        'timezone',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'competition_id');
    }
}
