<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Weekly platform detection for all active domains
Schedule::command('domains:detect-platforms --all')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->timezone('UTC');
