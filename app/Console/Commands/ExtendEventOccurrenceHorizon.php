<?php

namespace App\Console\Commands;

use App\Domain\Events\EventOccurrenceMaterializer;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Console\Command;

class ExtendEventOccurrenceHorizon extends Command
{
    protected $signature = 'events:extend-occurrence-horizon';

    protected $description = 'Materialize event occurrences through rolling horizon.';

    public function handle(EventOccurrenceMaterializer $materializer): int
    {
        $from = now()->subMonths(3)->toImmutable();
        $through = now()->addMonths(18)->toImmutable();
        Event::query()->where('status', EventStatus::Published)->orderBy('id')->each(
            fn (Event $event) => $materializer->materialize($event, $from, $through),
        );
        $this->info('Event occurrence horizon extended.');

        return self::SUCCESS;
    }
}
