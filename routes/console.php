<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:scheduled-database-backup')
    ->dailyAt('02:00')
    ->withoutOverlapping(180);

Schedule::command('app:operational-health-check')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
