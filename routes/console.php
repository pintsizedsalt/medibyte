<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Five minutes is how close to a chosen lead time — 30, 15, or 5 minutes before a
// dose — a reminder can actually land.
Schedule::command('medibyte:send-reminders')->everyFiveMinutes()->withoutOverlapping();
