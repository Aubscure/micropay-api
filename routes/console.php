<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Run the recovery command every 10 minutes.
// Laravel Cloud runs the scheduler automatically on the free tier.
Schedule::command('micropay:recover-stuck')->everyTenMinutes();