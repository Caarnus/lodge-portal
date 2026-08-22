<?php

namespace App\Console\Commands;

use App\Domain\Events\VolunteerReminderDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DispatchVolunteerReminders extends Command
{
    protected $signature = 'events:dispatch-volunteer-reminders';

    protected $description = 'Create and dispatch due volunteer staffing reminders.';

    public function handle(VolunteerReminderDispatcher $dispatcher): int
    {
        $count = $dispatcher->dispatchDue(CarbonImmutable::now());
        $this->info("Dispatched {$count} volunteer reminder(s).");

        return self::SUCCESS;
    }
}
