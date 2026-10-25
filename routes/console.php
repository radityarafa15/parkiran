<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sensor yang berhenti melapor tidak memicu event; siarkan ulang ringkasan tiap menit.
Schedule::command('parkir:broadcast-summaries')->everyMinute();
