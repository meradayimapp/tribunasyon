<?php

namespace App\Services\Football;

use App\Exceptions\LiveFootballApiException;
use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class FootballDataSynchronizer
{
    private const COMMUNITY_TEAM_SLUGS = [
        '8lroq0cbhdxj8124qtxwrhvmm' => 'fenerbahce',
        'esa748l653sss1wurz5ps3228' => 'galatasaray',
        '2ez9cvam9lp9jyhng3eh3znb4' => 'besiktas',
        '2yab38jdfl0gk2tei1mq40o06' => 'trabzonspor',
    ];

    public function __construct(private readonly LiveFootballApiService $api) {}

    public function syncCompetition(FootballCompetition $competition): int
    {
        $this->ensureSupportedProvider($competition);

        $data = $this->api->leagueFixtures($competition->provider_league_id);

        if ($data['league_id'] !== $competition->provider_league_id) {
            throw new LiveFootballApiException('Fikstür yanıtındaki lig kimliği istenen ligle eşleşmiyor.');
        }

        return DB::transaction(function () use ($competition, $data): int {
            $competition->update(array_filter([
                'name' => $this->nullableString($data['league_name'] ?? null),
                'current_season' => $this->nullableString($data['season'] ?? null),
                'timezone' => $this->nullableString($data['timezone'] ?? null),
            ], fn (mixed $value): bool => $value !== null));

            $count = 0;

            foreach ($data['weeks'] as $week) {
                foreach ($week['matches'] as $match) {
                    $this->syncMatch(
                        competition: $competition,
                        match: $match,
                        fallbackDate: null,
                        season: $this->nullableString($data['season'] ?? null),
                        week: $this->nullableString($week['week'] ?? null),
                    );
                    $count++;
                }
            }

            return $count;
        });
    }

    public function syncDate(Carbon|string $date): array
    {
        $dateString = $date instanceof Carbon ? $date->format('Y-m-d') : $date;
        $competitions = FootballCompetition::query()
            ->active()
            ->where('provider', LiveFootballApiService::PROVIDER)
            ->get()
            ->keyBy('provider_league_id');

        if ($competitions->isEmpty()) {
            return ['synced' => 0, 'ignored' => 0];
        }

        $data = $this->api->matchesForDate($dateString);

        return DB::transaction(function () use ($competitions, $data, $dateString): array {
            $synced = 0;
            $ignored = 0;

            foreach ($data['matches'] as $match) {
                $leagueId = $this->nullableString(data_get($match, 'league.id'));
                $competition = $leagueId ? $competitions->get($leagueId) : null;

                if (! $competition) {
                    $ignored++;

                    continue;
                }

                $this->syncMatch(
                    competition: $competition,
                    match: $match,
                    fallbackDate: $dateString,
                    season: null,
                    week: $this->nullableString($match['week'] ?? null),
                );
                $synced++;
            }

            return compact('synced', 'ignored');
        });
    }

    private function syncMatch(
        FootballCompetition $competition,
        mixed $match,
        ?string $fallbackDate,
        ?string $season,
        ?string $week,
    ): FootballMatch {
        if (! is_array($match)) {
            throw new UnexpectedValueException('Maç kaydı dizi olmalıdır.');
        }

        $matchId = $this->requiredString($match['id'] ?? null, 'Maç kimliği eksik.');
        $home = $this->requiredTeam($match['home'] ?? null, 'Ev sahibi takım verisi eksik.');
        $away = $this->requiredTeam($match['away'] ?? null, 'Deplasman takımı verisi eksik.');
        $syncedAt = CarbonImmutable::now('UTC');
        $homeTeam = $this->syncTeam($home, $syncedAt);
        $awayTeam = $this->syncTeam($away, $syncedAt);
        $status = is_array($match['status'] ?? null) ? $match['status'] : [];

        $attributes = [
            'competition_id' => $competition->id,
            'home_football_team_id' => $homeTeam->id,
            'away_football_team_id' => $awayTeam->id,
            'kickoff_at' => $this->kickoffAt($match, $fallbackDate),
            'status' => $this->nullableString($status['status'] ?? null) ?? 'unknown',
            'state' => $this->nullableString($status['state'] ?? null),
            'status_display' => $this->nullableString($status['display'] ?? null),
            'is_live' => $this->boolean($status['is_live'] ?? false),
            'last_synced_at' => $syncedAt,
            'meta' => $match,
        ];

        if ($season !== null) {
            $attributes['season'] = $season;
        }

        if ($week !== null) {
            $attributes['week'] = $week;
        }

        if (array_key_exists('round', $match)) {
            $attributes['round'] = $this->nullableString($match['round']);
        }

        $this->addScore($attributes, 'home_score', $home, 'score');
        $this->addScore($attributes, 'away_score', $away, 'score');
        $this->addScore($attributes, 'home_halftime_score', $match['halftime'] ?? null, 'home');
        $this->addScore($attributes, 'away_halftime_score', $match['halftime'] ?? null, 'away');
        $this->addScore($attributes, 'home_penalty_score', $match['penalty'] ?? null, 'home');
        $this->addScore($attributes, 'away_penalty_score', $match['penalty'] ?? null, 'away');

        if (array_key_exists('tv_broadcast', $match)) {
            $attributes['tv_broadcast'] = $this->boolean($match['tv_broadcast']);
        }

        return FootballMatch::updateOrCreate(
            ['provider' => LiveFootballApiService::PROVIDER, 'provider_match_id' => $matchId],
            $attributes,
        );
    }

    private function syncTeam(array $team, CarbonImmutable $syncedAt): FootballTeam
    {
        $providerTeamId = $this->requiredString($team['id'] ?? null, 'Takım kimliği eksik.');
        $attributes = [
            'provider_name' => $this->requiredString($team['name'] ?? null, 'Takım adı eksik.'),
            'is_active' => true,
            'last_synced_at' => $syncedAt,
        ];

        if (array_key_exists('logo', $team)) {
            $attributes['provider_logo_url'] = $this->nullableString($team['logo']);
        }

        if (array_key_exists('country', $team)) {
            $attributes['country'] = $this->nullableString($team['country']);
        }

        $communityTeamId = $this->communityTeamId($providerTeamId);

        if ($communityTeamId !== null) {
            $attributes['team_id'] = $communityTeamId;
        }

        return FootballTeam::updateOrCreate(
            ['provider' => LiveFootballApiService::PROVIDER, 'provider_team_id' => $providerTeamId],
            $attributes,
        );
    }

    private function kickoffAt(array $match, ?string $fallbackDate): CarbonImmutable
    {
        $date = $this->nullableString($match['date'] ?? null) ?? $fallbackDate;
        $kickoff = $this->nullableString($match['kickoff'] ?? null);

        if (! $date || ! $kickoff || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $kickoff)) {
            throw new UnexpectedValueException('Maç başlama tarihi veya saati geçersiz.');
        }

        $format = strlen($kickoff) === 5 ? '!Y-m-d H:i' : '!Y-m-d H:i:s';
        $parsed = CarbonImmutable::createFromFormat($format, "{$date} {$kickoff}", 'UTC');

        if (! $parsed || $parsed->format('Y-m-d H:i') !== "{$date} ".substr($kickoff, 0, 5)) {
            throw new UnexpectedValueException('Maç başlama tarihi veya saati geçersiz.');
        }

        return $parsed;
    }

    private function requiredTeam(mixed $team, string $message): array
    {
        if (! is_array($team)) {
            throw new UnexpectedValueException($message);
        }

        return $team;
    }

    private function requiredString(mixed $value, string $message): string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            throw new UnexpectedValueException($message);
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function addScore(array &$attributes, string $attribute, mixed $source, string $key): void
    {
        if (is_array($source) && array_key_exists($key, $source)) {
            $attributes[$attribute] = $this->score($source[$key]);
        }
    }

    private function score(mixed $score): ?int
    {
        if (is_int($score) && $score >= 0) {
            return $score;
        }

        if (is_string($score) && preg_match('/^\d+$/', trim($score))) {
            return (int) trim($score);
        }

        return null;
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function communityTeamId(string $providerTeamId): ?int
    {
        $normalizedId = str_starts_with($providerTeamId, 'lfa-') ? substr($providerTeamId, 4) : $providerTeamId;
        $slug = self::COMMUNITY_TEAM_SLUGS[$normalizedId] ?? null;

        return $slug ? Team::query()->where('slug', $slug)->value('id') : null;
    }

    private function ensureSupportedProvider(FootballCompetition $competition): void
    {
        if ($competition->provider !== LiveFootballApiService::PROVIDER) {
            throw new LiveFootballApiException("{$competition->provider} sağlayıcısı desteklenmiyor.");
        }
    }
}
