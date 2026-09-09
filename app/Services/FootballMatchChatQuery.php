<?php

namespace App\Services;

use App\Models\FootballMatch;
use Illuminate\Database\Eloquent\Collection;

class FootballMatchChatQuery
{
    public const BATCH_SIZE = 50;

    public const POLL_SIZE = 100;

    public function latest(FootballMatch $match): array
    {
        $messages = $this->base($match)->latest('id')->limit(self::BATCH_SIZE + 1)->get();

        return [
            'messages' => $messages->take(self::BATCH_SIZE)->values(),
            'has_older' => $messages->count() > self::BATCH_SIZE,
        ];
    }

    public function before(FootballMatch $match, int $beforeId): array
    {
        $messages = $this->base($match)
            ->where('id', '<', $beforeId)
            ->latest('id')
            ->limit(self::BATCH_SIZE + 1)
            ->get();

        return [
            'messages' => $messages->take(self::BATCH_SIZE)->values(),
            'has_older' => $messages->count() > self::BATCH_SIZE,
        ];
    }

    public function after(FootballMatch $match, int $afterId): Collection
    {
        return $this->base($match)
            ->where('id', '>', $afterId)
            ->oldest('id')
            ->limit(self::POLL_SIZE)
            ->get();
    }

    public function delta(FootballMatch $match, int $afterId): array
    {
        $delta = $match->chatMessages()
            ->where('id', '>', $afterId)
            ->selectRaw('COUNT(*) as aggregate, MAX(id) as maximum_id')
            ->first();

        return [
            'count' => (int) ($delta?->aggregate ?? 0),
            'max_id' => (int) ($delta?->maximum_id ?? $afterId),
        ];
    }

    private function base(FootballMatch $match)
    {
        return $match->chatMessages()
            ->select(['id', 'football_match_id', 'user_id', 'body', 'created_at'])
            ->with('user:id,name,username,avatar_path,role');
    }
}
