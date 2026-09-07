<?php

namespace App\Models;

use App\Enums\TeamStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'short_name', 'logo', 'cover_image', 'organization_badge', 'organization_id', 'primary_color', 'secondary_color', 'status', 'sort_order'];

    protected function casts(): array
    {
        return ['status' => TeamStatus::class, 'sort_order' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TeamStatus::Active);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class, 'current_team_id');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_follows')->withTimestamps();
    }

    public function moderators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_moderator')->withTimestamps();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return str_starts_with($this->logo, 'images/')
            ? asset($this->logo)
            : Storage::disk('public')->url($this->logo);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
