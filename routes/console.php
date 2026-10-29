<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rattrape les reels programmés. Nécessite la tâche cron `schedule:run` à la minute.
Schedule::command('reels:notify-due')->everyFiveMinutes()->withoutOverlapping();
