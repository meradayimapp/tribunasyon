<?php

namespace App\Services\Football;

class PlayerPositionFormatter
{
    private const LABELS = [
        'goalkeeper' => 'Kaleci', 'keeper' => 'Kaleci', 'gk' => 'Kaleci', 'kaleci' => 'Kaleci',
        'defender' => 'Defans', 'defence' => 'Defans', 'defense' => 'Defans', 'df' => 'Defans', 'defans' => 'Defans',
        'midfielder' => 'Orta saha', 'midfield' => 'Orta saha', 'mf' => 'Orta saha', 'orta saha' => 'Orta saha',
        'attacker' => 'Forvet', 'forward' => 'Forvet', 'fw' => 'Forvet', 'forvet' => 'Forvet',
    ];

    public static function format(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $withoutExecutableContent = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', (string) $value);
        $position = mb_substr(trim(strip_tags($withoutExecutableContent ?? '')), 0, 80);

        return $position === '' ? null : (self::LABELS[mb_strtolower($position, 'UTF-8')] ?? $position);
    }

    public static function isKnown(mixed $value): bool
    {
        $position = self::format($value);

        return $position !== null && isset(self::LABELS[mb_strtolower(trim((string) $value), 'UTF-8')]);
    }
}
