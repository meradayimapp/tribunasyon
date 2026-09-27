<?php

namespace App\Models;

use App\Services\MediaUrlResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FootballCompetition extends Model
{
    public const SUPER_LEAGUE_PROVIDER_ID = '482ofyysbdbeoxauk19yg7tdt';

    public const NATIONS_LEAGUE_PROVIDER_ID = '595nsvo7ykvoe690b1e4u5n56';

    public const CHAMPIONS_LEAGUE_PROVIDER_ID = '4oogyu6o156iphvdvphwpck10';

    public const EUROPA_LEAGUE_PROVIDER_ID = '4c1nfi2j1m731hcay25fcgndq';

    public const CONFERENCE_LEAGUE_PROVIDER_ID = 'c7b8o53flg36wbuevfzy3lb10';

    public const PREMIER_LEAGUE_PROVIDER_ID = '2kwbbcootiqqgmrzs6o5inle5';

    public const LA_LIGA_PROVIDER_ID = '34pl8szyvrbwcmfkuocjm3r6t';

    public const SERIE_A_PROVIDER_ID = '1r097lpxe0xn03ihb7wi98kao';

    public const BUNDESLIGA_PROVIDER_ID = '6by3h89i2eykc341oz7lv1ddd';

    public const LIGUE_1_PROVIDER_ID = 'dm5ka0os1e3dxcp3vh05kmp33';

    public const PRIMEIRA_LIGA_PROVIDER_ID = '2w4n0fgjq0wy8nttjw0td7s3z';

    public const FEATURED_PROVIDER_LEAGUE_IDS = [
        self::SUPER_LEAGUE_PROVIDER_ID,
        self::NATIONS_LEAGUE_PROVIDER_ID,
        self::CHAMPIONS_LEAGUE_PROVIDER_ID,
        self::EUROPA_LEAGUE_PROVIDER_ID,
        self::CONFERENCE_LEAGUE_PROVIDER_ID,
        self::PREMIER_LEAGUE_PROVIDER_ID,
        self::LA_LIGA_PROVIDER_ID,
        self::SERIE_A_PROVIDER_ID,
        self::BUNDESLIGA_PROVIDER_ID,
        self::LIGUE_1_PROVIDER_ID,
        self::PRIMEIRA_LIGA_PROVIDER_ID,
    ];

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

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->whereIn('provider_league_id', self::FEATURED_PROVIDER_LEAGUE_IDS);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'competition_id');
    }

    public function logoUrl(): ?string
    {
        return $this->provider_logo_url ?: app(MediaUrlResolver::class)->url($this->logo_path);
    }

    public function regionLabel(): string
    {
        if (filled($this->country)) {
            return $this->country;
        }

        return in_array($this->provider_league_id, [
            self::NATIONS_LEAGUE_PROVIDER_ID,
            self::CHAMPIONS_LEAGUE_PROVIDER_ID,
            self::EUROPA_LEAGUE_PROVIDER_ID,
            self::CONFERENCE_LEAGUE_PROVIDER_ID,
        ], true) ? 'Avrupa' : 'Uluslararası';
    }
}
