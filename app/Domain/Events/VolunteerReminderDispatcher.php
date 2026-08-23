<?php

namespace App\Domain\Events;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\VolunteerCommitmentStatus;
use App\Enums\VolunteerReminderDeliveryStatus;
use App\Jobs\SendVolunteerReminderDelivery;
use App\Models\EventVolunteerCommitment;
use App\Models\EventVolunteerReminderDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

class VolunteerReminderDispatcher
{
    public function dispatchDue(CarbonImmutable $now): int
    {
        $offset = max(1, (int)config('events.volunteer_reminder_offset_minutes', 1440));
        EventVolunteerCommitment::query()->with(['user', 'position', 'occurrence.event'])->where('status', VolunteerCommitmentStatus::Committed)->whereHas('position', fn($query) => $query->where('is_active', true))->whereHas('occurrence', fn($query) => $query->where('status', EventOccurrenceStatus::Scheduled)->where('starts_at', '>', $now))->whereHas('event', fn($query) => $query->where('status', EventStatus::Published))->orderBy('id')->each(function (EventVolunteerCommitment $commitment) use ($offset, $now): void {
            if ($commitment->position->event_occurrence_id !== null && $commitment->position->event_occurrence_id !== $commitment->event_occurrence_id) {
                return;
            }
            $email = $commitment->user?->email;
            try {
                EventVolunteerReminderDelivery::query()->firstOrCreate(['event_volunteer_commitment_id' => $commitment->id], ['event_volunteer_position_id' => $commitment->event_volunteer_position_id, 'event_occurrence_id' => $commitment->event_occurrence_id, 'event_id' => $commitment->event_id, 'lodge_id' => $commitment->lodge_id, 'recipient_email' => $email, 'normalized_recipient_email' => $email ? mb_strtolower(trim($email)) : null, 'due_at' => $commitment->occurrence->starts_at->subMinutes($offset)->max($now), 'status' => VolunteerReminderDeliveryStatus::Pending]);
            } catch (QueryException) {
            }
        });
        $ids = EventVolunteerReminderDelivery::query()->where('status', VolunteerReminderDeliveryStatus::Pending)->where('due_at', '<=', $now)->orderBy('id')->limit(100)->pluck('id');
        $claimed = 0;
        foreach ($ids as $id) {
            if (EventVolunteerReminderDelivery::query()->whereKey($id)->where('status', VolunteerReminderDeliveryStatus::Pending)->update(['status' => VolunteerReminderDeliveryStatus::Claimed, 'claimed_at' => $now]) === 1) {
                SendVolunteerReminderDelivery::dispatch($id);
                $claimed++;
            }
        }

        return $claimed;
    }
}
