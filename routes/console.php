<?php

use Illuminate\Support\Facades\Schedule;

// All notification timing is Port Moresby wall-clock time (also the app
// timezone) — set explicitly here so a future timezone change can't
// silently shift these.
Schedule::command('events:notify-today')
    ->dailyAt('08:00')
    ->timezone('Pacific/Port_Moresby');

Schedule::command('gym:notify-upcoming')
    ->everyFiveMinutes()
    ->timezone('Pacific/Port_Moresby');
