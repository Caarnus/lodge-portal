<?php

namespace App\Http\Controllers;

use App\Domain\Events\VolunteerEligibility;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\VolunteerCommitmentStatus;
use App\Enums\VolunteerReminderDeliveryStatus;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventVolunteerReminderDelivery;
use App\Models\Lodge;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventVolunteerReminderDeliveryController extends Controller
{
    public function retry(Request $request, Lodge $lodge, Event $event, EventOccurrence $occurrence, EventVolunteerReminderDelivery $delivery, VolunteerEligibility $eligibility)
    {
        abort_unless($event->lodge_id === $lodge->id && $occurrence->event_id === $event->id && $occurrence->lodge_id === $lodge->id && $delivery->event_occurrence_id === $occurrence->id && $delivery->event_id === $event->id && $delivery->lodge_id === $lodge->id, 404);
        abort_unless($request->user()?->hasLodgePermission($lodge, 'events.manage'), 403);
        $delivery->load(['commitment.user', 'position']);
        if ($delivery->status !== VolunteerReminderDeliveryStatus::Failed || $delivery->commitment?->status !== VolunteerCommitmentStatus::Committed || ! $delivery->position?->is_active || $event->status !== EventStatus::Published || $occurrence->status !== EventOccurrenceStatus::Scheduled || ! $occurrence->starts_at->isFuture() || ! $delivery->commitment->user || ! $eligibility->canVolunteer($delivery->commitment->user, $event)) {
            throw ValidationException::withMessages(['delivery' => 'This reminder cannot be retried.']);
        }
        $dueAt = $occurrence->starts_at->copy()->subMinutes(max(1, (int) config('events.volunteer_reminder_offset_minutes', 1440)));
        $delivery->update(['status' => VolunteerReminderDeliveryStatus::Pending, 'attempted_at' => null, 'claimed_at' => null, 'failed_at' => null, 'last_error' => null, 'due_at' => $dueAt->isPast() ? now() : $dueAt]);

        return back();
    }
}
