<?php

namespace App\Domain\Events;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\VolunteerCommitmentStatus;
use App\Enums\VolunteerReminderDeliveryStatus;
use App\Models\EventOccurrence;
use App\Models\EventVolunteerCommitment;
use App\Models\EventVolunteerPosition;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VolunteerCommitmentService
{
    public function __construct(private readonly VolunteerEligibility $eligibility) {}

    public function commit(EventOccurrence $occurrence, EventVolunteerPosition $position, User $target, User $actor): EventVolunteerCommitment
    {
        return DB::transaction(function () use ($occurrence, $position, $target, $actor) {
            $occurrence = EventOccurrence::query()->with(['event.lodge'])->findOrFail($occurrence->id);
            $position = EventVolunteerPosition::query()->lockForUpdate()->findOrFail($position->id);
            $event = $occurrence->event;
            if ($position->event_id !== $event->id || $position->lodge_id !== $event->lodge_id || ($position->event_occurrence_id !== null && $position->event_occurrence_id !== $occurrence->id)) {
                abort(404);
            }
            if ($event->status !== EventStatus::Published || $occurrence->status !== EventOccurrenceStatus::Scheduled || ! $occurrence->starts_at->isFuture() || ! $position->is_active) {
                throw ValidationException::withMessages(['position' => 'Volunteer staffing is unavailable for this occurrence.']);
            }
            if (! $this->eligibility->canVolunteer($target, $event)) {
                abort(403);
            }
            $active = EventVolunteerCommitment::query()->where('event_volunteer_position_id', $position->id)->where('event_occurrence_id', $occurrence->id)->where('status', VolunteerCommitmentStatus::Committed);
            if ($active->where('person_id', $target->person_id)->exists()) {
                throw ValidationException::withMessages(['position' => 'You already committed to this position.']);
            }
            if ((clone $active)->count() >= $position->needed_count) {
                throw ValidationException::withMessages(['position' => 'This volunteer position is full.']);
            }

            $commitment = EventVolunteerCommitment::create(['event_volunteer_position_id' => $position->id, 'event_occurrence_id' => $occurrence->id, 'event_id' => $event->id, 'lodge_id' => $event->lodge_id, 'user_id' => $target->id, 'person_id' => $target->person_id, 'status' => VolunteerCommitmentStatus::Committed, 'committed_at' => now(), 'created_by' => $actor->id]);
            Audit::record('volunteer_commitment.created', $commitment, $event->lodge, null, $commitment->toArray());

            return $commitment;
        });
    }

    public function withdraw(EventVolunteerCommitment $commitment, User $actor, bool $manager = false): EventVolunteerCommitment
    {
        return DB::transaction(function () use ($commitment, $actor, $manager) {
            $commitment = EventVolunteerCommitment::query()->with(['occurrence', 'event'])->lockForUpdate()->findOrFail($commitment->id);
            if ($commitment->status !== VolunteerCommitmentStatus::Committed) {
                throw ValidationException::withMessages(['commitment' => 'This commitment is already inactive.']);
            }
            if (! $manager && ($commitment->user_id !== $actor->id || $commitment->person_id !== $actor->person_id || ! $commitment->occurrence->starts_at->isFuture())) {
                abort(403);
            }
            $values = $manager ? ['status' => VolunteerCommitmentStatus::AdministrativelyRemoved, 'administratively_removed_at' => now(), 'removed_by' => $actor->id] : ['status' => VolunteerCommitmentStatus::Withdrawn, 'withdrawn_at' => now()];
            $before = $commitment->toArray();
            $commitment->update($values);
            $commitment->reminderDelivery()->whereIn('status', [VolunteerReminderDeliveryStatus::Pending, VolunteerReminderDeliveryStatus::Claimed])->update(['status' => VolunteerReminderDeliveryStatus::Skipped, 'skip_reason' => 'commitment_inactive', 'skipped_at' => now()]);
            $commitment = $commitment->refresh();
            Audit::record($manager ? 'volunteer_commitment.administratively_removed' : 'volunteer_commitment.withdrawn', $commitment, $commitment->lodge, $before, $commitment->toArray());

            return $commitment;
        });
    }
}
