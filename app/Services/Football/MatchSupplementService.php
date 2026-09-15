<?php

namespace App\Services\Football;

use App\Models\FootballMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MatchSupplementService
{
    public function __construct(private readonly LiveFootballApiService $api) {}

    public function headToHead(FootballMatch $match): ?array
    {
        return $this->cached($match, 'h2h', 720, fn (): array => $this->normalizeHeadToHead(
            $this->api->headToHead($match->provider_match_id),
            $match->homeTeam->provider_team_id,
            $match->awayTeam->provider_team_id,
        ));
    }

    public function injuries(FootballMatch $match): ?array
    {
        return $this->cached($match, 'injuries', 60, fn (): array => $this->normalizeInjuries(
            $this->api->injuries($match->provider_match_id),
        ));
    }

    private function cached(FootballMatch $match, string $type, int $minutes, callable $fetch): ?array
    {
        if ($match->provider !== LiveFootballApiService::PROVIDER || trim((string) $match->provider_match_id) === '') {
            return null;
        }

        $key = "football:match:{$type}:{$match->provider}:{$match->provider_match_id}";
        $staleKey = $key.':stale';

        try {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return ($cached['unavailable'] ?? false) ? Cache::get($staleKey) : $cached;
            }

            $lock = Cache::lock($key.':lock', 20);
            if (! $lock->get()) {
                $stale = Cache::get($staleKey);

                return is_array($stale) ? $stale : null;
            }

            try {
                $cached = Cache::get($key);
                if (is_array($cached)) {
                    return ($cached['unavailable'] ?? false) ? Cache::get($staleKey) : $cached;
                }

                $result = $fetch();
                Cache::put($key, $result, now()->addMinutes($minutes));
                Cache::put($staleKey, $result, now()->addDay());

                return $result;
            } finally {
                $lock->release();
            }
        } catch (Throwable $exception) {
            Log::warning('Maç ek verisi alınamadı.', [
                'type' => $type, 'match_id' => $match->id, 'exception' => $exception::class,
            ]);

            try {
                Cache::put($key, ['unavailable' => true], now()->addMinutes(10));
                $stale = Cache::get($staleKey);

                return is_array($stale) ? $stale : null;
            } catch (Throwable) {
                return null;
            }
        }
    }

    public function normalizeHeadToHead(array $data, string $homeId, string $awayId): array
    {
        $history = $this->matches($data['h2h'] ?? []);
        $summary = is_array($data['h2h_summary'] ?? null) ? $data['h2h_summary'] : [];
        $counts = [];

        foreach (['home_wins', 'draws', 'away_wins'] as $field) {
            $value = $summary[$field] ?? null;
            $counts[$field] = is_int($value) && $value >= 0 ? $value : null;
        }

        return [
            'home_form' => $this->form($this->matches($data['home_form'] ?? []), $homeId),
            'away_form' => $this->form($this->matches($data['away_form'] ?? []), $awayId),
            'history' => array_slice($history, 0, 10),
            'summary' => in_array(null, $counts, true) ? null : $counts,
        ];
    }

    public function normalizeInjuries(array $data): array
    {
        $injuries = is_array($data['injuries'] ?? null) ? $data['injuries'] : [];
        $result = [];

        foreach (['home', 'away'] as $side) {
            $result[$side] = [];

            foreach (array_slice(is_array($injuries[$side] ?? null) ? $injuries[$side] : [], 0, 30) as $player) {
                if (! is_array($player)) {
                    continue;
                }

                $name = $this->string($player['name'] ?? null);
                $status = $this->string($player['status'] ?? null);
                if ($name === null || $status === null) {
                    continue;
                }

                $image = $this->string($player['image'] ?? null, 500);
                $result[$side][] = [
                    'name' => $name,
                    'status' => $status,
                    'position' => $this->string($player['position'] ?? null),
                    'image' => $image !== null && filter_var($image, FILTER_VALIDATE_URL) && str_starts_with($image, 'https://') ? $image : null,
                ];
            }
        }

        return $result;
    }

    private function matches(mixed $matches): array
    {
        if (! is_array($matches)) {
            return [];
        }

        $result = [];
        foreach (array_slice($matches, 0, 30) as $match) {
            if (! is_array($match)) {
                continue;
            }

            $home = is_array($match['home'] ?? null) ? $match['home'] : [];
            $away = is_array($match['away'] ?? null) ? $match['away'] : [];
            $score = $this->string($match['score'] ?? null, 12);

            if ($this->string($home['id'] ?? null) === null || $this->string($away['id'] ?? null) === null
                || $this->string($home['name'] ?? null) === null || $this->string($away['name'] ?? null) === null
                || $score === null || ! preg_match('/^\d{1,2}\s*[-–]\s*\d{1,2}$/u', $score)) {
                continue;
            }

            $result[] = [
                'date' => $this->string($match['date'] ?? null, 20),
                'home_id' => $this->string($home['id']), 'home_name' => $this->string($home['name']),
                'away_id' => $this->string($away['id']), 'away_name' => $this->string($away['name']),
                'score' => preg_replace('/\s+/', '', $score),
            ];
        }

        return $result;
    }

    private function form(array $matches, string $teamId): array
    {
        $results = [];
        foreach ($matches as $match) {
            $isHome = $match['home_id'] === $teamId;
            if (! $isHome && $match['away_id'] !== $teamId) {
                continue;
            }

            $scores = preg_split('/[-–]/u', $match['score']);
            $ours = (int) $scores[$isHome ? 0 : 1];
            $theirs = (int) $scores[$isHome ? 1 : 0];
            $results[] = $ours > $theirs ? 'W' : ($ours < $theirs ? 'L' : 'D');
        }

        return array_slice($results, 0, 5);
    }

    private function string(mixed $value, int $limit = 120): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
