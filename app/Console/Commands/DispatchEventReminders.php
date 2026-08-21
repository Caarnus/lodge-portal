<?php

namespace App\Console\Commands;

use App\Domain\Events\EventReminderDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DispatchEventReminders extends Command
{
    protected $signature = 'events:dispatch-reminders';

    protected $description = 'Create and dispatch due event reminder deliveries.';

    public function handle(EventReminderDispatcher $dispatcher): int
    {
        $claimed = $dispatcher->dispatchDue(CarbonImmutable::now());
        $this->info("Dispatched {$claimed} event reminder(s).");

        return self::SUCCESS;
    }
}
