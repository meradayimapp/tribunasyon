<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Database\Eloquent\Collection;

class PlayerChatQuery
{
    public const BATCH_SIZE = 50;

    public const POLL_SIZE = 100;

    public function latest(Player $player): array
    {
        $messages = $this->base($player)
            ->latest('id')
            ->limit(self::BATCH_SIZE + 1)
            ->get();
        $hasOlder = $messages->count() > self::BATCH_SIZE;
        $messages = $messages->take(self::BATCH_SIZE)->values();

        return ['messages' => $messages, 'has_older' => $hasOlder];
    }

    public function before(Player $player, int $beforeId): array
    {
        $messages = $this->base($player)
            ->where('id', '<', $beforeId)
            ->latest('id')
            ->limit(self::BATCH_SIZE + 1)
            ->get();
        $hasOlder = $messages->count() > self::BATCH_SIZE;
        $messages = $messages->take(self::BATCH_SIZE)->values();

        return ['messages' => $messages, 'has_older' => $hasOlder];
    }

    public function after(Player $player, int $afterId): Collection
    {
        return $this->base($player)
            ->where('id', '>', $afterId)
            ->oldest('id')
            ->limit(self::POLL_SIZE)
            ->get();
    }

    public function delta(Player $player, int $afterId): array
    {
        $delta = $player->chatMessages()
            ->where('id', '>', $afterId)
            ->selectRaw('COUNT(*) as aggregate, MAX(id) as maximum_id')
            ->first();

        return [
            'count' => (int) ($delta?->aggregate ?? 0),
            'max_id' => (int) ($delta?->maximum_id ?? $afterId),
        ];
    }

    private function base(Player $player)
    {
        return $player->chatMessages()
            ->select(['id', 'player_id', 'user_id', 'body', 'created_at'])
            ->with('user:id,name,username,avatar_path,role');
    }
}
