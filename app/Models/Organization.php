<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Services\MediaUrlResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'logo_path', 'status'];

    protected function casts(): array
    {
        return ['status' => OrganizationStatus::class];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', OrganizationStatus::Active);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function logoUrl(): ?string
    {
        return app(MediaUrlResolver::class)->url($this->logo_path);
    }
}
