<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('football:sync-live')->everyMinute()->withoutOverlapping(2);
Schedule::command('football:sync-daily')->dailyAt('05:10')->withoutOverlapping(10);
Schedule::command('football:sync-fixtures')->weeklyOn(1, '04:10')->withoutOverlapping(30);
