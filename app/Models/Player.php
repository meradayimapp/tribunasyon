<?php

namespace App\Models;

use App\Enums\PlayerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Player extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'photo_path', 'cover_image_path', 'position', 'shirt_number',
        'current_team_id', 'national_team_name', 'national_team_code', 'birth_date',
        'market_value_amount', 'market_value_currency', 'bio', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'market_value_amount' => 'integer',
            'shirt_number' => 'integer',
            'sort_order' => 'integer',
            'status' => PlayerStatus::class,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PlayerStatus::Active);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    public function scopeMatching(Builder $query, string $term): Builder
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($term));
        $pattern = "%{$escaped}%";

        return $query->where(function (Builder $players) use ($pattern): void {
            $players->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("slug LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('currentTeam', fn (Builder $teams) => $teams->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]));
        });
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id')->withTrashed();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'player_follows')->withTimestamps();
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(PlayerChatMessage::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    public function getFormattedMarketValueAttribute(): ?string
    {
        if ($this->market_value_amount === null || ! $this->market_value_currency) {
            return null;
        }

        $amount = $this->market_value_amount;
        [$value, $suffix] = match (true) {
            $amount >= 1_000_000_000 => [$amount / 1_000_000_000, 'B'],
            $amount >= 1_000_000 => [$amount / 1_000_000, 'M'],
            $amount >= 1_000 => [$amount / 1_000, 'K'],
            default => [$amount, ''],
        };
        $decimals = $value == floor($value) ? 0 : 1;
        $formatted = number_format($value, $decimals, '.', '');
        if ($decimals > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }
        $symbol = match ($this->market_value_currency) {
            'EUR' => '€',
            'GBP' => '£',
            'USD' => '$',
            'TRY' => '₺',
            default => $this->market_value_currency.' ',
        };

        return $symbol.$formatted.$suffix;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function coverImageUrl(): ?string
    {
        return $this->cover_image_path ? Storage::disk('public')->url($this->cover_image_path) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
