<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('events:dispatch-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('events:dispatch-volunteer-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('events:extend-occurrence-horizon')->daily()->withoutOverlapping();
Schedule::command('newsletters:purge-family-requests')->daily()->withoutOverlapping();
Schedule::command('communications:dispatch')->everyMinute()->withoutOverlapping();
