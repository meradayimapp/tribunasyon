<?php

namespace App\Services\Football;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeagueStandingsService
{
    private const CACHE_MINUTES = 30;

    private const STALE_HOURS = 24;

    public function __construct(private readonly LiveFootballApiService $api) {}

    public function forLeague(string $leagueId, ?string $season = null): ?array
    {
        $leagueId = trim($leagueId);
        $season = filled($season) ? trim((string) $season) : null;

        if ($leagueId === '') {
            return null;
        }

        $cacheKey = $this->cacheKey($leagueId, $season);
        $staleKey = $cacheKey.':stale';
        $cached = $this->cached($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $stale = $this->cached($staleKey);

        try {
            $standings = $this->normalize($this->api->leagueStandings($leagueId, $season));
        } catch (Throwable $exception) {
            Log::warning('Lig puan durumu alınamadı.', [
                'league_id' => $leagueId,
                'exception' => $exception::class,
            ]);

            return $stale;
        }

        try {
            Cache::put($cacheKey, $standings, now()->addMinutes(self::CACHE_MINUTES));
            Cache::put($staleKey, $standings, now()->addHours(self::STALE_HOURS));
        } catch (Throwable $exception) {
            Log::warning('Lig puan durumu cache içine yazılamadı.', [
                'league_id' => $leagueId,
                'exception' => $exception::class,
            ]);
        }

        return $standings;
    }

    public function normalize(array $data): array
    {
        $tables = [];

        foreach ($data['standings'] ?? [] as $group) {
            if (! is_array($group) || ! is_array($group['table'] ?? null)) {
                continue;
            }

            $rows = [];

            foreach ($group['table'] as $row) {
                $normalized = $this->normalizeRow($row);

                if ($normalized !== null) {
                    $rows[] = $normalized;
                }
            }

            if ($rows !== []) {
                $tables[] = [
                    'title' => $this->nullableString($group['title'] ?? null),
                    'rows' => $rows,
                ];
            }
        }

        return [
            'league_id' => $this->nullableString($data['league_id'] ?? null),
            'season' => $this->nullableString($data['season'] ?? null),
            'available_seasons' => $this->stringList($data['available_seasons'] ?? null),
            'tables' => $tables,
        ];
    }

    private function normalizeRow(mixed $row): ?array
    {
        if (! is_array($row) || ! is_array($row['team'] ?? null)) {
            return null;
        }

        $teamId = $this->nullableString($row['team']['id'] ?? null);
        $teamName = $this->nullableString($row['team']['name'] ?? null);
        $rank = $this->integer($row['rank'] ?? null, allowNegative: false);
        $numeric = [];

        foreach (['played', 'won', 'drawn', 'lost', 'goals_for', 'goals_against', 'points'] as $field) {
            $numeric[$field] = $this->integer($row[$field] ?? null, allowNegative: false);
        }

        $goalDiff = $this->integer($row['goal_diff'] ?? null, allowNegative: true);

        if ($teamId === null || $teamName === null || $rank === null || $goalDiff === null || in_array(null, $numeric, true)) {
            return null;
        }

        $zone = is_array($row['zone'] ?? null) ? $row['zone'] : [];
        $form = $this->nullableString($row['form'] ?? null);
        $form = $form !== null ? strtoupper($form) : null;

        if ($form === null || ! preg_match('/^[WDL]{1,5}$/', $form)) {
            $form = null;
        }

        return [
            'rank' => $rank,
            'provider_team_id' => $teamId,
            'team_name' => $teamName,
            'team_logo' => $this->safeUrl($row['team']['logo'] ?? null),
            'played' => $numeric['played'],
            'won' => $numeric['won'],
            'drawn' => $numeric['drawn'],
            'lost' => $numeric['lost'],
            'goals_for' => $numeric['goals_for'],
            'goals_against' => $numeric['goals_against'],
            'goal_diff' => $goalDiff,
            'points' => $numeric['points'],
            'form' => $form,
            'zone_name' => $this->nullableString($zone['name'] ?? null),
            'zone_color' => $this->safeColor($zone['color'] ?? null),
        ];
    }

    private function cached(string $key): ?array
    {
        try {
            $value = Cache::get($key);
        } catch (Throwable) {
            return null;
        }

        return is_array($value) && is_array($value['tables'] ?? null) ? $value : null;
    }

    private function cacheKey(string $leagueId, ?string $season): string
    {
        return 'football:standings:'.$leagueId.':'.($season ?: 'current');
    }

    private function integer(mixed $value, bool $allowNegative): ?int
    {
        if (is_int($value)) {
            return ($allowNegative || $value >= 0) ? $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        $pattern = $allowNegative ? '/^-?\d+$/' : '/^\d+$/';

        return preg_match($pattern, $value) ? (int) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map($this->nullableString(...), $values)));
    }

    private function safeUrl(mixed $value): ?string
    {
        $url = $this->nullableString($value);

        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
    }

    private function safeColor(mixed $value): ?string
    {
        $color = $this->nullableString($value);

        return $color !== null && preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : null;
    }
}
