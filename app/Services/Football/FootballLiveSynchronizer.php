<?php

namespace App\Services\Football;

use App\Models\FootballMatch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Throwable;

class FootballLiveSynchronizer
{
    private const PRE_MATCH_WINDOW_MINUTES = 20;

    private const MATCH_WINDOW_HOURS = 6;

    private const DETAIL_THROTTLE_SECONDS = 45;

    private const LINEUP_WINDOW_MINUTES = 75;

    private const LINEUP_AFTER_KICKOFF_MINUTES = 10;

    private const LINEUP_RETRY_MINUTES = 20;

    public function __construct(
        private readonly LiveFootballApiService $api,
        private readonly FootballDataSynchronizer $fixtures,
    ) {}

    public function sync(): array
    {
        $now = CarbonImmutable::now('UTC');
        $candidates = $this->candidateQuery($now)->get(['football_matches.id', 'football_matches.kickoff_at']);

        $dates = $candidates
            ->map(fn (FootballMatch $match): string => $match->kickoffInDisplayTimezone()->format('Y-m-d'))
            ->unique();
        $dateCalls = 0;
        $failed = 0;
        $details = $this->syncLiveDetails($now, $failed);

        foreach ($dates as $date) {
            $throttleKey = "football:live:matches:{$date}";
            if (! Cache::add($throttleKey, true, 55)) {
                continue;
            }

            try {
                $this->fixtures->syncDate($date);
                $dateCalls++;
            } catch (Throwable) {
                Cache::forget($throttleKey);
                $failed++;
            }
        }

        $details += $this->syncLiveDetails($now, $failed);

        $lineups = 0;
        $lineupMatches = $this->lineupCandidateQuery($now)->get();

        foreach ($lineupMatches as $match) {
            $throttleKey = "football:lineups:{$match->provider}:{$match->provider_match_id}";
            if (! Cache::add($throttleKey, true, now()->addMinutes(self::LINEUP_RETRY_MINUTES))) {
                continue;
            }

            try {
                if ($this->syncLineups($match)) {
                    $lineups++;
                }
            } catch (Throwable) {
                Cache::forget($throttleKey);
                $failed++;
            }
        }

        return ['candidates' => $candidates->count(), 'dates' => $dateCalls, 'details' => $details, 'lineups' => $lineups, 'failed' => $failed];
    }

    private function syncLiveDetails(CarbonImmutable $now, int &$failed): int
    {
        $liveMatches = $this->candidateQuery($now)
            ->where('football_matches.is_live', true)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('football_matches.live_details_synced_at')
                ->orWhere('football_matches.live_details_synced_at', '<=', $now->subSeconds(self::DETAIL_THROTTLE_SECONDS)->format('Y-m-d H:i:s')))
            ->get();
        $details = 0;

        foreach ($liveMatches as $match) {
            $throttleKey = "football:live:details:{$match->provider}:{$match->provider_match_id}";
            if (! Cache::add($throttleKey, true, self::DETAIL_THROTTLE_SECONDS)) {
                continue;
            }

            try {
                $this->syncDetails($match);
                $details++;
            } catch (Throwable) {
                Cache::forget($throttleKey);
                $failed++;
            }
        }

        return $details;
    }

    public function syncDetails(FootballMatch $match): void
    {
        if ($match->provider !== LiveFootballApiService::PROVIDER || ! $match->is_live) {
            return;
        }

        $data = $this->api->liveMatchDetails($match->provider_match_id);
        $header = $data['header'];
        $status = is_array($header['status'] ?? null) ? $header['status'] : [];
        $attributes = [
            'last_synced_at' => CarbonImmutable::now('UTC'),
            'live_details_synced_at' => CarbonImmutable::now('UTC'),
        ];

        $this->addScore($attributes, 'home_score', data_get($header, 'home.score'));
        $this->addScore($attributes, 'away_score', data_get($header, 'away.score'));

        if (array_key_exists('display', $status)) {
            $attributes['status_display'] = $this->nullableString($status['display']);
        }
        if (array_key_exists('state', $status)) {
            $attributes['state'] = $this->nullableString($status['state']);
        }
        if (array_key_exists('minute', $status)) {
            $attributes['live_minute'] = $this->minute($status['minute']);
        }
        if (array_key_exists('is_live', $status)) {
            $attributes['is_live'] = filter_var($status['is_live'], FILTER_VALIDATE_BOOLEAN);
            $attributes['status'] = $attributes['is_live'] ? 'live' : $this->statusAfterLive($attributes['state'] ?? $match->state);
        }
        if (is_array($data['events'] ?? null)) {
            $attributes['live_events'] = $this->normalizeEvents($data['events']);
        }
        if (is_array($data['stats'] ?? null)) {
            $attributes['match_stats'] = $this->normalizeStats($data['stats']);
        }

        $venueName = $this->nullableString(data_get($data, 'venue.name'), 160);
        if ($venueName !== null) {
            $attributes['venue_name'] = $venueName;
        }

        $refereeName = $this->nullableString($data['referee'] ?? null, 120);
        if ($refereeName !== null) {
            $attributes['referee_name'] = $refereeName;
        }

        if (is_array($data['tv_channels'] ?? null)) {
            $attributes['tv_channels'] = array_values(array_filter(array_map(
                fn (mixed $channel): ?string => $this->nullableString($channel, 80),
                array_slice($data['tv_channels'], 0, 12),
            )));
        }

        $match->update($attributes);
    }

    public function syncLineups(FootballMatch $match): bool
    {
        if ($match->provider !== LiveFootballApiService::PROVIDER || $match->isFinished()) {
            return false;
        }

        $data = $this->api->lineups($match->provider_match_id);
        $lineups = $this->normalizeLineups($data);
        $hasStartingPlayers = ($lineups['home']['starting'] ?? []) !== []
            || ($lineups['away']['starting'] ?? []) !== [];

        $attributes = [
            'lineup_synced_at' => CarbonImmutable::now('UTC'),
            'lineup_is_projected' => filter_var($data['is_projected'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        if ($hasStartingPlayers) {
            $attributes['lineups'] = $lineups;
        }

        $match->update($attributes);

        return $hasStartingPlayers;
    }

    private function candidateQuery(CarbonImmutable $now): Builder
    {
        return FootballMatch::query()
            ->whereBetween('football_matches.kickoff_at', [
                $now->subHours(self::MATCH_WINDOW_HOURS)->format('Y-m-d H:i:s'),
                $now->addMinutes(self::PRE_MATCH_WINDOW_MINUTES)->format('Y-m-d H:i:s'),
            ])
            ->where(fn (Builder $query): Builder => $query
                ->where('football_matches.is_live', true)
                ->orWhereNotIn('football_matches.status', FootballMatch::TERMINAL_STATUSES))
            ->where('football_matches.provider', LiveFootballApiService::PROVIDER)
            ->whereHas('competition', fn (Builder $query): Builder => $query->active()
                ->where('provider', LiveFootballApiService::PROVIDER));
    }

    private function lineupCandidateQuery(CarbonImmutable $now): Builder
    {
        return FootballMatch::query()
            ->whereBetween('football_matches.kickoff_at', [
                $now->subMinutes(self::LINEUP_AFTER_KICKOFF_MINUTES)->format('Y-m-d H:i:s'),
                $now->addMinutes(self::LINEUP_WINDOW_MINUTES)->format('Y-m-d H:i:s'),
            ])
            ->where('football_matches.provider', LiveFootballApiService::PROVIDER)
            ->whereNotIn('football_matches.status', FootballMatch::TERMINAL_STATUSES)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('football_matches.lineups')
                ->orWhere('football_matches.lineup_is_projected', true))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('football_matches.lineup_synced_at')
                ->orWhere('football_matches.lineup_synced_at', '<=', $now->subMinutes(self::LINEUP_RETRY_MINUTES)->format('Y-m-d H:i:s')))
            ->whereHas('competition', fn (Builder $query): Builder => $query->active()
                ->where('provider', LiveFootballApiService::PROVIDER));
    }

    private function normalizeEvents(array $events): array
    {
        $types = [
            'goal' => 'Gol',
            'yellow card' => 'Sarı Kart',
            'red card' => 'Kırmızı Kart',
            'substitution' => 'Oyuncu Değişikliği',
        ];
        $normalized = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $providerType = strtolower((string) ($event['type'] ?? ''));
            if (! isset($types[$providerType])) {
                continue;
            }

            $time = $this->eventTime($event['time'] ?? null);
            $side = in_array($event['side'] ?? null, ['home', 'away'], true) ? $event['side'] : null;
            $detail = is_array($event['detail'] ?? null) ? $event['detail'] : [];
            $playerName = $this->personName($detail['player'] ?? null);

            if ($providerType === 'substitution') {
                $out = $this->personName($detail['player_out'] ?? null);
                $in = $this->personName($detail['player_in'] ?? null);
                $playerName = $out && $in ? "{$out} → {$in}" : ($in ?? $out ?? $playerName);
            }

            $score = $this->scoreLine($detail['score'] ?? null);
            $normalized[] = array_filter([
                'time' => $time,
                'type' => str_replace(' ', '_', $providerType),
                'label' => $types[$providerType],
                'side' => $side,
                'player_name' => $playerName,
                'player_in' => $in ?? null,
                'player_out' => $out ?? null,
                'score' => $score,
            ], fn (mixed $value): bool => $value !== null);
        }

        return $normalized;
    }

    private function normalizeLineups(array $data): array
    {
        $lineups = [];

        foreach (['home', 'away'] as $side) {
            $team = is_array($data[$side] ?? null) ? $data[$side] : [];
            $lineups[$side] = [
                'starting' => $this->normalizePlayers($team['starting'] ?? []),
                'subs' => $this->normalizePlayers($team['subs'] ?? []),
            ];

            $coach = $this->normalizePerson($team['coach'] ?? null);
            if ($coach !== null) {
                $lineups[$side]['coach'] = $coach;
            }
        }

        $formation = is_array($data['formation'] ?? null) ? array_filter([
            'home' => $this->nullableString($data['formation']['home'] ?? null, 20),
            'away' => $this->nullableString($data['formation']['away'] ?? null, 20),
        ]) : [];
        if ($formation !== []) {
            $lineups['formation'] = $formation;
        }
        $lineups['is_projected'] = filter_var($data['is_projected'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $lineups;
    }

    private function normalizePlayers(mixed $players): array
    {
        if (! is_array($players)) {
            return [];
        }

        $normalized = [];
        foreach (array_slice($players, 0, 30) as $player) {
            $person = $this->normalizePerson($player);
            if ($person === null) {
                continue;
            }

            $person['number'] = $this->nullableString($player['number'] ?? null, 4);
            $person['position'] = $this->nullableString($player['position'] ?? null, 60);
            $normalized[] = array_filter($person, fn (mixed $value): bool => $value !== null);
        }

        return $normalized;
    }

    private function normalizePerson(mixed $person): ?array
    {
        if (! is_array($person)) {
            return null;
        }

        $name = $this->nullableString($person['name'] ?? null, 120);
        if ($name === null) {
            return null;
        }

        $image = $this->nullableString($person['image'] ?? null, 500);
        if ($image !== null && (! filter_var($image, FILTER_VALIDATE_URL) || ! str_starts_with($image, 'https://'))) {
            $image = null;
        }

        return array_filter([
            'id' => $this->nullableString($person['id'] ?? null, 120),
            'name' => $name,
            'image' => $image,
        ], fn (mixed $value): bool => $value !== null);
    }

    private function normalizeStats(array $stats): array
    {
        $labels = [
            'possession' => 'Topa Sahip Olma',
            'shots' => 'Şut',
            'shots on target' => 'İsabetli Şut',
            'corners' => 'Korner',
            'fouls' => 'Faul',
            'offsides' => 'Ofsayt',
        ];
        $normalized = [];

        foreach (array_slice($stats, 0, 24) as $stat) {
            if (! is_array($stat)) {
                continue;
            }

            $providerLabel = $this->nullableString($stat['label'] ?? null, 60);
            $home = $this->nullableString($stat['home'] ?? null, 24);
            $away = $this->nullableString($stat['away'] ?? null, 24);
            if ($providerLabel === null || ($home === null && $away === null)) {
                continue;
            }

            $normalized[] = [
                'label' => $labels[strtolower($providerLabel)] ?? $providerLabel,
                'home' => $home,
                'away' => $away,
            ];
        }

        return $normalized;
    }

    private function addScore(array &$attributes, string $key, mixed $value): void
    {
        if ((is_int($value) || (is_string($value) && preg_match('/^\d{1,3}$/', trim($value)))) && (int) $value >= 0) {
            $attributes[$key] = (int) $value;
        }
    }

    private function minute(mixed $value): ?int
    {
        if ((is_int($value) || (is_string($value) && preg_match('/^\d{1,3}$/', trim($value)))) && (int) $value >= 0 && (int) $value <= 150) {
            return (int) $value;
        }

        return null;
    }

    private function statusAfterLive(?string $state): string
    {
        $normalized = strtolower((string) preg_replace('/[^a-z]/i', '', (string) $state));

        return in_array($normalized, ['postgame', 'fulltime', 'finished', 'ended', 'aftergame'], true)
            ? 'finished'
            : 'unknown';
    }

    private function eventTime(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return preg_match('/^\d{1,3}(?:\+\d{1,2})?$/', $value) ? $value : null;
    }

    private function scoreLine(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return preg_match('/^\d{1,3}\s*-\s*\d{1,3}$/', $value) ? preg_replace('/\s+/', '', $value) : null;
    }

    private function personName(mixed $person): ?string
    {
        return is_array($person) ? $this->nullableString($person['name'] ?? null, 120) : null;
    }

    private function nullableString(mixed $value, int $maxLength = 80): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }
}
