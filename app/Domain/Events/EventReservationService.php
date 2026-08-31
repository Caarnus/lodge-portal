<?php

namespace App\Domain\Events;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventReservationStatus;
use App\Enums\EventStatus;
use App\Models\EventOccurrence;
use App\Models\EventReservation;
use App\Models\EventReservationField;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventReservationService
{
    public function __construct(private readonly EventEligibility $eligibility)
    {
    }

    public function reserve(EventOccurrence $occurrence, ?User $user, array $data): ReservationResult
    {
        return DB::transaction(function () use ($occurrence, $user, $data): ReservationResult {
            $occurrence = EventOccurrence::query()->with('event')->lockForUpdate()->findOrFail($occurrence->id);
            $event = $occurrence->event;
            $this->ensurePermitted($occurrence, $user, $data);
            $responses = $this->validatedResponses($event->id, $data['responses'] ?? []);
            $normalizedEmail = mb_strtolower(trim($data['email']));
            if (EventReservation::query()->where('event_occurrence_id', $occurrence->id)->where('normalized_email', $normalizedEmail)->where('status', EventReservationStatus::Confirmed)->exists()) {
                throw ValidationException::withMessages(['email' => 'An active reservation already exists for this email address.']);
            }
            $partySize = (int)($data['party_size'] ?? 1);
            $reserved = (int)EventReservation::query()->where('event_occurrence_id', $occurrence->id)->where('status', EventReservationStatus::Confirmed)->sum('party_size');
            if ($reserved + $partySize > $event->capacity) {
                throw ValidationException::withMessages(['party_size' => 'There is not enough remaining capacity for this reservation.']);
            }
            $token = bin2hex(random_bytes(32));
            $reservation = EventReservation::create([
                'event_occurrence_id' => $occurrence->id, 'event_id' => $event->id, 'lodge_id' => $event->lodge_id,
                'user_id' => $user?->id, 'person_id' => $user?->person_id, 'name' => $data['name'], 'email' => $data['email'],
                'normalized_email' => $normalizedEmail, 'phone' => $data['phone'] ?? null, 'party_size' => $partySize,
                'responses' => $responses ?: null, 'status' => EventReservationStatus::Confirmed,
                'cancellation_token_hash' => hash('sha256', $token),
            ]);

            return new ReservationResult($reservation, $token);
        });
    }

    private function ensurePermitted(EventOccurrence $occurrence, ?User $user, array $data): void
    {
        $event = $occurrence->event;
        if ($event->status !== EventStatus::Published || $occurrence->status !== EventOccurrenceStatus::Scheduled || !$event->reservations_enabled || !$event->capacity) {
            throw ValidationException::withMessages(['event' => 'Reservations are unavailable for this occurrence.']);
        }
        if ($user && !$this->eligibility->canReserve($user, $event)) {
            abort(403);
        }
        if (!$user && (!$event->guest_reservations_enabled || !$this->eligibility->canView(null, $event))) {
            abort(403);
        }
        if (($event->maximum_party_size ?? PHP_INT_MAX) < (int)($data['party_size'] ?? 1)) {
            throw ValidationException::withMessages(['party_size' => 'This party size exceeds the event limit.']);
        }
    }

    private function validatedResponses(int $eventId, mixed $responses): array
    {
        if (!is_array($responses)) {
            throw ValidationException::withMessages(['responses' => 'Reservation responses are invalid.']);
        }

        $fields = EventReservationField::query()->where('event_id', $eventId)->where('is_active', true)->get()->keyBy('key');
        $unknown = array_diff(array_keys($responses), $fields->keys()->all());
        if ($unknown) {
            throw ValidationException::withMessages(['responses' => 'Reservation responses contain an unavailable field.']);
        }
        foreach ($fields as $key => $field) {
            $value = $responses[$key] ?? null;
            if ($field->is_required && ($value === null || $value === '' || $value === false)) {
                throw ValidationException::withMessages(["responses.{$key}" => "{$field->label} is required."]);
            }
            if ($value === null || $value === '') {
                continue;
            }
            $valid = match ($field->type->value) {
                'short_text' => is_string($value) && mb_strlen($value) <= 255,
                'long_text' => is_string($value) && mb_strlen($value) <= 5000,
                'select' => is_string($value) && in_array($value, $field->options ?? [], true),
                'checkbox' => is_bool($value),
            };
            if (!$valid) {
                throw ValidationException::withMessages(["responses.{$key}" => "{$field->label} is invalid."]);
            }
        }

        return $responses;
    }
}
