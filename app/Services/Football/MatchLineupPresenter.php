<?php

namespace App\Services\Football;

use App\Models\Player;

class MatchLineupPresenter
{
    public function present(mixed $lineups): array
    {
        if (! is_array($lineups)) {
            return [];
        }

        $ids = [];
        foreach (['home', 'away'] as $side) {
            $team = is_array($lineups[$side] ?? null) ? $lineups[$side] : [];
            foreach (['starting', 'subs'] as $group) {
                $players = is_array($team[$group] ?? null) ? $team[$group] : [];
                foreach ($players as $player) {
                    if (is_array($player) && is_scalar($player['id'] ?? null) && trim((string) $player['id']) !== '') {
                        $ids[] = (string) $player['id'];
                    }
                }
            }
        }

        $localPlayers = $ids === [] ? collect() : Player::query()
            ->active()
            ->whereIn('provider_player_id', array_unique($ids))
            ->get(['id', 'slug', 'provider_player_id', 'photo_path', 'provider_image_url', 'status'])
            ->keyBy('provider_player_id');

        foreach (['home', 'away'] as $side) {
            if (! is_array($lineups[$side] ?? null)) {
                continue;
            }

            foreach (['starting', 'subs'] as $group) {
                if (! is_array($lineups[$side][$group] ?? null)) {
                    continue;
                }

                foreach ($lineups[$side][$group] as &$player) {
                    if (! is_array($player)) {
                        continue;
                    }

                    $player['position_display'] = PlayerPositionFormatter::format($player['position'] ?? null);
                    $local = $localPlayers->get((string) ($player['id'] ?? ''));
                    if ($local) {
                        $player['profile_url'] = route('players.show', $local);
                        $player['image'] = $local->displayImageUrl() ?: Player::safeProviderImageUrl($player['image'] ?? null);
                    } else {
                        $player['image'] = Player::safeProviderImageUrl($player['image'] ?? null);
                    }
                }
                unset($player);
            }
        }

        return $lineups;
    }
}
