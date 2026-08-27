<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'avatar_path', 'bio',
        'favorite_team_id', 'role', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function favoriteTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'favorite_team_id');
    }

    public function followedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_follows')->withTimestamps();
    }

    public function moderatedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_moderator')->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isModerator(): bool
    {
        return $this->role === UserRole::Moderator;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function getRouteKeyName(): string
    {
        return 'username';
    }
}
