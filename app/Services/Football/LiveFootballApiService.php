<?php

namespace App\Services\Football;

use App\Exceptions\LiveFootballApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class LiveFootballApiService
{
    public const PROVIDER = 'live-football-api';

    public function leagueFixtures(string $leagueId, ?string $season = null): array
    {
        $query = ['league_id' => $leagueId];

        if (filled($season)) {
            $query['season'] = $season;
        }

        $data = $this->get('league_fixtures', $query);

        if (! is_string($data['league_id'] ?? null) || ! is_array($data['weeks'] ?? null)) {
            throw new LiveFootballApiException('Fikstür yanıtı beklenen veri yapısında değil.');
        }

        foreach ($data['weeks'] as $week) {
            if (! is_array($week) || ! is_array($week['matches'] ?? null)) {
                throw new LiveFootballApiException('Fikstür haftaları beklenen veri yapısında değil.');
            }
        }

        return $data;
    }

    public function matchesForDate(Carbon|string $date): array
    {
        $date = $date instanceof Carbon ? $date->format('Y-m-d') : $date;

        try {
            $parsedDate = Carbon::createFromFormat('!Y-m-d', $date, 'UTC');
        } catch (Throwable) {
            $parsedDate = null;
        }

        if (! $parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            throw new LiveFootballApiException('Maç tarihi YYYY-MM-DD biçiminde olmalıdır.');
        }

        $data = $this->get('matches', ['date' => $date]);

        if (! is_array($data['matches'] ?? null)) {
            throw new LiveFootballApiException('Günlük maç yanıtı beklenen veri yapısında değil.');
        }

        return $data;
    }

    private function get(string $endpoint, array $query): array
    {
        $key = trim((string) config('services.live_football_api.key'));
        $baseUrl = rtrim((string) config('services.live_football_api.base_url'), '/');

        if ($key === '') {
            throw new LiveFootballApiException('LIVE_FOOTBALL_API_KEY tanımlı değil.');
        }

        if ($baseUrl === '') {
            throw new LiveFootballApiException('LIVE_FOOTBALL_API_BASE_URL tanımlı değil.');
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout(12)
                ->connectTimeout(5)
                ->retry(3, 300, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && in_array($exception->response->status(), [500, 503], true)), throw: false)
                ->get($endpoint, ['api_key' => $key, 'lang' => 'tr'] + $query);
        } catch (Throwable) {
            throw new LiveFootballApiException('Live Football API bağlantısı kurulamadı.');
        }

        $this->ensureSuccessful($response);

        $payload = $response->json();

        if (! is_array($payload) || ($payload['success'] ?? null) !== true || ! is_array($payload['data'] ?? null)) {
            throw new LiveFootballApiException('Live Football API geçersiz bir yanıt döndürdü.');
        }

        return $payload['data'];
    }

    private function ensureSuccessful(Response $response): void
    {
        if (! $response->successful()) {
            throw new LiveFootballApiException("Live Football API isteği başarısız oldu (HTTP {$response->status()}).");
        }
    }
}
