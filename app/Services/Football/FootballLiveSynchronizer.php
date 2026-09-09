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

    public function __construct(
        private readonly LiveFootballApiService $api,
        private readonly FootballDataSynchronizer $fixtures,
    ) {}

    public function sync(): array
    {
        $now = CarbonImmutable::now('UTC');
        $candidates = $this->candidateQuery($now)->get(['football_matches.id', 'football_matches.kickoff_at']);

        if ($candidates->isEmpty()) {
            return ['candidates' => 0, 'dates' => 0, 'details' => 0, 'failed' => 0];
        }

        $dates = $candidates
            ->map(fn (FootballMatch $match): string => $match->kickoffInDisplayTimezone()->format('Y-m-d'))
            ->unique();
        $dateCalls = 0;
        $failed = 0;

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

        return ['candidates' => $candidates->count(), 'dates' => $dateCalls, 'details' => $details, 'failed' => $failed];
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

        $match->update($attributes);
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
                'score' => $score,
            ], fn (mixed $value): bool => $value !== null);
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
