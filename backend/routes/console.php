<?php

use App\Console\Commands\GenerateDailyReport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(GenerateDailyReport::class)->dailyAt('22:00');
