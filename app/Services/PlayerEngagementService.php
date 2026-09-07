<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PlayerEngagementService
{
    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    public const MANUAL = 'manual';

    public const SORTS = [self::WEEKLY, self::MONTHLY, self::MANUAL];

    public function applyRanking(Builder $query, string $period): Builder
    {
        if ($period === self::MANUAL) {
            return $query->ordered();
        }

        $since = $this->since($period);

        return $query
            ->withCount([
                'chatMessages as period_messages_count' => fn (Builder $messages) => $messages->where('created_at', '>=', $since),
                'followers as period_follows_count' => fn (Builder $followers) => $followers->where('player_follows.created_at', '>=', $since),
            ])
            ->orderByDesc('period_messages_count')
            ->orderByDesc('period_follows_count')
            ->ordered();
    }

    public function badgesFor(Player $player): array
    {
        $badges = [];

        foreach ([self::WEEKLY => 'Haftanın en çok konuşulanı', self::MONTHLY => 'Ayın en çok konuşulanı'] as $period => $label) {
            $leader = $this->applyRanking(Player::query()->active(), $period)->first();
            $interactions = (int) ($leader?->period_messages_count ?? 0) + (int) ($leader?->period_follows_count ?? 0);

            if ($leader?->is($player) && $interactions > 0) {
                $badges[] = $label;
            }
        }

        return $badges;
    }

    public function since(string $period): Carbon
    {
        return $period === self::MONTHLY ? now()->startOfMonth() : now()->startOfWeek();
    }
}
