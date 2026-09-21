<?php

namespace Tests\Feature\Football;

use App\Services\Football\FootballLiveSynchronizer;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class FootballScheduleTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_football_schedules_are_named_in_process_callbacks_with_the_existing_timing_and_mutexes(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_starts_with((string) $event->description, 'football:sync-'))
            ->keyBy(fn ($event): string => $event->description);

        $this->assertCount(3, $events);

        $expected = [
            'football:sync-live' => ['* * * * *', 2],
            'football:sync-daily' => ['10 5 * * *', 10],
            'football:sync-fixtures' => ['10 4 * * 1', 30],
        ];

        foreach ($expected as $name => [$expression, $expiresAt]) {
            $event = $events->get($name);

            $this->assertInstanceOf(CallbackEvent::class, $event);
            $this->assertNull($event->command);
            $this->assertSame($name, $event->description);
            $this->assertSame($expression, $event->expression);
            $this->assertSame('Europe/Istanbul', $event->timezone);
            $this->assertTrue($event->withoutOverlapping);
            $this->assertSame($expiresAt, $event->expiresAt);
            $this->assertSame('framework/schedule-'.sha1($name), $event->mutexName());
        }
    }

    public function test_schedule_run_executes_the_live_sync_command_in_process(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 12:34:00', 'Europe/Istanbul'));

        $this->mock(FootballLiveSynchronizer::class)
            ->shouldReceive('sync')
            ->once()
            ->andReturn([
                'candidates' => 0,
                'details' => 0,
                'lineups' => 0,
                'candidate_matches' => [],
                'failures' => [],
                'failed' => 0,
            ]);

        $this->artisan('schedule:run')
            ->expectsOutputToContain('football:sync-live')
            ->assertExitCode(0);
    }

    public function test_non_zero_sync_exit_code_fails_the_scheduled_callback(): void
    {
        $this->mock(FootballLiveSynchronizer::class)
            ->shouldReceive('sync')
            ->once()
            ->andThrow(new RuntimeException('Provider failed.'));

        $liveEvent = collect(app(Schedule::class)->events())
            ->firstWhere('description', 'football:sync-live');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('football:sync-live failed with exit code 1');

        $liveEvent->run($this->app);
    }
}
