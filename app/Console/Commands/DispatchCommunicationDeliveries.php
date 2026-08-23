<?php

namespace App\Console\Commands;

use App\Enums\CommunicationDeliveryStatus;
use App\Jobs\SendCommunicationDelivery;
use App\Models\CommunicationDelivery;
use Illuminate\Console\Command;

class DispatchCommunicationDeliveries extends Command
{
    protected $signature = 'communications:dispatch';

    protected $description = 'Dispatch pending lodge communication email deliveries.';

    public function handle(): int
    {
        $ids = CommunicationDelivery::query()->where('channel', 'email')->where(function ($q) {
            $q->where('status', CommunicationDeliveryStatus::Pending)->orWhere(fn($stale) => $stale->where('status', CommunicationDeliveryStatus::Claimed)->where('claimed_at', '<=', now()->subMinutes(15)));
        })->limit(100)->pluck('id');
        foreach ($ids as $id) {
            if (CommunicationDelivery::query()->whereKey($id)->where(function ($q) {
                $q->where('status', CommunicationDeliveryStatus::Pending)->orWhere(fn($stale) => $stale->where('status', CommunicationDeliveryStatus::Claimed)->where('claimed_at', '<=', now()->subMinutes(15)));
            })->update(['status' => CommunicationDeliveryStatus::Claimed, 'claimed_at' => now()])) {
                SendCommunicationDelivery::dispatch($id);
            }
        }

        return self::SUCCESS;
    }
}
