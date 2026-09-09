<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FootballMatchChatMessage extends Model
{
    use SoftDeletes;

    protected $fillable = ['football_match_id', 'user_id', 'body'];

    public function footballMatch(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
