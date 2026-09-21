<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\ResetPasswordNotification;
use App\Services\MediaUrlResolver;
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
        'name', 'username', 'email', 'password', 'password_set_at', 'avatar_path', 'google_id', 'google_avatar_url', 'bio',
        'favorite_team_id', 'role', 'status', 'anonymized_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_set_at' => 'datetime',
            'anonymized_at' => 'datetime',
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

    public function followedPlayers(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'player_follows')->withTimestamps();
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

    public function playerChatMessages(): HasMany
    {
        return $this->hasMany(PlayerChatMessage::class);
    }

    public function footballMatchChatMessages(): HasMany
    {
        return $this->hasMany(FootballMatchChatMessage::class);
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

    public function hasUsablePassword(): bool
    {
        return $this->password_set_at !== null;
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    public function displayName(): string
    {
        return $this->isAnonymized() ? 'Silinmiş kullanıcı' : $this->name;
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->displayName(), 0, 2));
    }

    public function displayAvatarUrl(): ?string
    {
        if (filled($this->avatar_path)) {
            return app(MediaUrlResolver::class)->url($this->avatar_path);
        }

        if (filled($this->google_avatar_url)
            && filter_var($this->google_avatar_url, FILTER_VALIDATE_URL)
            && parse_url($this->google_avatar_url, PHP_URL_SCHEME) === 'https') {
            return $this->google_avatar_url;
        }

        return null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function getRouteKeyName(): string
    {
        return 'username';
    }
}
