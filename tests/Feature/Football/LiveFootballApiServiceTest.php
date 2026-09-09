<?php

namespace Tests\Feature\Football;

use App\Exceptions\LiveFootballApiException;
use App\Services\Football\LiveFootballApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveFootballApiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.live_football_api', [
            'key' => 'test-secret-key',
            'base_url' => 'https://football.test/api/v1',
        ]);
    }

    public function test_it_requests_and_validates_league_fixtures(): void
    {
        Http::fake([
            'football.test/api/v1/league_fixtures*' => Http::response([
                'success' => true,
                'data' => [
                    'league_id' => 'league-1',
                    'weeks' => [['week' => '1', 'matches' => []]],
                ],
            ]),
        ]);

        $data = app(LiveFootballApiService::class)->leagueFixtures('league-1', '2026/2027');

        $this->assertSame('league-1', $data['league_id']);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://football.test/api/v1/league_fixtures?')
                && ($query['api_key'] ?? null) === 'test-secret-key'
                && ($query['league_id'] ?? null) === 'league-1'
                && ($query['season'] ?? null) === '2026/2027'
                && ($query['lang'] ?? null) === 'tr';
        });
    }

    public function test_it_requests_matches_using_the_expected_date_format(): void
    {
        Http::fake([
            'football.test/api/v1/matches*' => Http::response([
                'success' => true,
                'data' => ['matches' => []],
            ]),
        ]);

        app(LiveFootballApiService::class)->matchesForDate(Carbon::parse('2026-09-08'));

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['date'] ?? null) === '2026-09-08';
        });
    }

    public function test_it_rejects_an_invalid_payload(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'data' => ['weeks' => 'invalid']]),
        ]);

        $this->expectException(LiveFootballApiException::class);
        $this->expectExceptionMessage('Fikstür yanıtı beklenen veri yapısında değil.');

        app(LiveFootballApiService::class)->leagueFixtures('league-1');
    }

    public function test_it_requests_and_validates_live_match_details_by_provider_id(): void
    {
        Http::fake([
            'football.test/api/v1/live_match_details*' => Http::response([
                'success' => true,
                'data' => ['match_id' => 'match-1', 'header' => ['status' => ['is_live' => true]]],
            ]),
        ]);

        $data = app(LiveFootballApiService::class)->liveMatchDetails('match-1');

        $this->assertSame('match-1', $data['match_id']);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['match_id'] ?? null) === 'match-1'
                && ($query['api_key'] ?? null) === 'test-secret-key';
        });
    }

    public function test_error_messages_do_not_expose_the_api_key(): void
    {
        Http::fake(['*' => Http::response(['success' => false], 401)]);

        try {
            app(LiveFootballApiService::class)->matchesForDate('2026-09-08');
            $this->fail('An exception should have been thrown.');
        } catch (LiveFootballApiException $exception) {
            $this->assertStringNotContainsString('test-secret-key', $exception->getMessage());
            $this->assertStringNotContainsString('football.test', $exception->getMessage());
            $this->assertSame('Live Football API isteği başarısız oldu (HTTP 401).', $exception->getMessage());
        }
    }
}
