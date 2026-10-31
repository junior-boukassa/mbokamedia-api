<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rattrape les reels programmés. Nécessite la tâche cron `schedule:run` à la minute.
Schedule::command('reels:notify-due')->everyFiveMinutes()->withoutOverlapping();

// Un seul fichier est traité par lancement; les reels restent brouillons jusqu’à validation.
Schedule::command('reels:process-transcode')->everyMinute()->withoutOverlapping(5);

// Le verrou flock partagé avec les reels sérialise les deux types de transcodage.
Schedule::command('briefings:process-audio')->everyMinute()->withoutOverlapping(5);
