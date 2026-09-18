<?php

namespace App\Services\Football;

class MatchFormationLayout
{
    public static function rows(mixed $formation, mixed $players): ?array
    {
        if (! is_array($players) || count($players) !== 11 || ! is_scalar($formation)
            || array_filter($players, fn (mixed $player): bool => ! is_array($player))) {
            return null;
        }

        $value = trim((string) $formation);
        if (! preg_match('/^\d(?:-?\d){2,4}$/', $value)) {
            return null;
        }

        $groups = str_contains($value, '-') ? explode('-', $value) : str_split($value);
        $counts = array_map('intval', $groups);
        if (array_sum($counts) !== 10 || min($counts) < 1 || max($counts) > 5) {
            return null;
        }

        $firstPosition = mb_strtolower((string) ($players[0]['position'] ?? ''));
        if (! in_array($firstPosition, ['goalkeeper', 'kaleci'], true)) {
            return null;
        }

        $rows = [[$players[0]]];
        $offset = 1;
        foreach ($counts as $count) {
            $rows[] = array_slice($players, $offset, $count);
            $offset += $count;
        }

        $role = static fn (array $player): string => mb_strtolower(trim((string) ($player['position'] ?? '')));
        if (array_filter($rows[1], fn (array $player): bool => ! in_array($role($player), ['defender', 'defans', 'savunma'], true))
            || array_filter($rows[array_key_last($rows)], fn (array $player): bool => ! in_array($role($player), ['attacker', 'forward', 'forvet'], true))) {
            return null;
        }

        foreach (array_slice($rows, 2, -1) as $middleRow) {
            if (array_filter($middleRow, fn (array $player): bool => ! in_array($role($player), ['midfielder', 'orta saha', 'attacker', 'forward', 'forvet'], true))) {
                return null;
            }
        }

        return $rows;
    }
}
