<?php

namespace App\Domain\Events;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\ReminderSubscriptionStatus;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventReminderSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventReminderSubscriptionService
{
    public function __construct(private readonly EventEligibility $eligibility) {}

    public function subscribe(Event $event, ?EventOccurrence $occurrence, ?User $user, array $data): ReminderSubscriptionResult
    {
        return DB::transaction(function () use ($event, $occurrence, $user, $data): ReminderSubscriptionResult {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            if ($occurrence) {
                $occurrence = EventOccurrence::query()->whereKey($occurrence->id)->where('event_id', $event->id)->where('lodge_id', $event->lodge_id)->firstOrFail();
            }
            $this->ensurePermitted($event, $occurrence, $user);
            $normalizedEmail = mb_strtolower(trim($data['email']));
            $query = EventReminderSubscription::query()->where('event_id', $event->id)->where('normalized_email', $normalizedEmail)->where('status', ReminderSubscriptionStatus::Active);
            $occurrence ? $query->where('event_occurrence_id', $occurrence->id) : $query->whereNull('event_occurrence_id');
            if ($query->exists()) {
                throw ValidationException::withMessages(['email' => 'An active reminder subscription already exists for this event scope.']);
            }
            $token = bin2hex(random_bytes(32));
            $subscription = EventReminderSubscription::create([
                'event_id' => $event->id, 'lodge_id' => $event->lodge_id, 'event_occurrence_id' => $occurrence?->id,
                'user_id' => $user?->id, 'person_id' => $user?->person_id, 'name' => $data['name'] ?? null,
                'email' => $data['email'], 'normalized_email' => $normalizedEmail, 'status' => ReminderSubscriptionStatus::Active,
                'unsubscribe_token_hash' => hash('sha256', $token),
            ]);

            return new ReminderSubscriptionResult($subscription, $token);
        });
    }

    private function ensurePermitted(Event $event, ?EventOccurrence $occurrence, ?User $user): void
    {
        if ($event->status !== EventStatus::Published || ! $event->reminders_enabled) {
            throw ValidationException::withMessages(['event' => 'Reminder subscriptions are unavailable for this event.']);
        }
        if ($occurrence && $occurrence->status !== EventOccurrenceStatus::Scheduled) {
            throw ValidationException::withMessages(['occurrence' => 'Reminder subscriptions are unavailable for this occurrence.']);
        }
        if ($user && ! $this->eligibility->canView($user, $event)) {
            abort(403);
        }
        if (! $user && (! $event->guest_reminders_enabled || ! $this->eligibility->canView(null, $event))) {
            abort(403);
        }
    }
}
