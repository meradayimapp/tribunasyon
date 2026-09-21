<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$runFootballCommand = static function (string $command): void {
    $exitCode = Artisan::call($command);

    if ($exitCode !== 0) {
        throw new RuntimeException("{$command} failed with exit code {$exitCode}");
    }
};

Schedule::call(fn () => $runFootballCommand('football:sync-live'))
    ->name('football:sync-live')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::call(fn () => $runFootballCommand('football:sync-daily'))
    ->name('football:sync-daily')
    ->dailyAt('05:10')
    ->withoutOverlapping(10);

Schedule::call(fn () => $runFootballCommand('football:sync-fixtures'))
    ->name('football:sync-fixtures')
    ->weeklyOn(1, '04:10')
    ->withoutOverlapping(30);
