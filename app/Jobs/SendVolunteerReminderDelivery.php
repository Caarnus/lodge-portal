<?php

namespace App\Jobs;

use App\Domain\Events\VolunteerEligibility;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\VolunteerCommitmentStatus;
use App\Enums\VolunteerReminderDeliveryStatus;
use App\Models\EventVolunteerReminderDelivery;
use App\Notifications\VolunteerStaffingReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendVolunteerReminderDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(VolunteerEligibility $eligibility): void
    {
        $delivery = EventVolunteerReminderDelivery::query()->with(['commitment.user.person', 'position', 'occurrence', 'event.lodge'])->find($this->deliveryId);
        if (! $delivery || $delivery->status !== VolunteerReminderDeliveryStatus::Claimed || $delivery->attempted_at) {
            return;
        }
        $commitment = $delivery->commitment;
        $user = $commitment?->user;
        $valid = $commitment && $user && $delivery->event_volunteer_position_id === $commitment->event_volunteer_position_id && $delivery->event_occurrence_id === $commitment->event_occurrence_id && $delivery->event_id === $commitment->event_id && $delivery->lodge_id === $commitment->lodge_id && $commitment->status === VolunteerCommitmentStatus::Committed && $delivery->position?->is_active && ($delivery->position->event_occurrence_id === null || $delivery->position->event_occurrence_id === $delivery->event_occurrence_id) && $delivery->event?->status === EventStatus::Published && $delivery->occurrence?->status === EventOccurrenceStatus::Scheduled && $delivery->occurrence->starts_at->isFuture() && $eligibility->canVolunteer($user, $delivery->event) && $user->email && $delivery->recipient_email === $user->email;
        if (! $valid) {
            $delivery->update(['status' => VolunteerReminderDeliveryStatus::Skipped, 'skip_reason' => 'account_unavailable', 'skipped_at' => now()]);

            return;
        }
        if (EventVolunteerReminderDelivery::query()->whereKey($delivery->id)->where('status', VolunteerReminderDeliveryStatus::Claimed)->whereNull('attempted_at')->update(['attempted_at' => now()]) !== 1) {
            return;
        }
        try {
            Notification::route('mail', $user->email)->notify(new VolunteerStaffingReminder($delivery));
            $delivery->update(['status' => VolunteerReminderDeliveryStatus::Sent, 'sent_at' => now(), 'last_error' => null]);
        } catch (\Throwable $exception) {
            $delivery->update(['status' => VolunteerReminderDeliveryStatus::Failed, 'failed_at' => now(), 'last_error' => str($exception->getMessage())->limit(1000)]);
        }
    }
}
