<?php

namespace Database\Factories;

use App\Enums\VolunteerReminderDeliveryStatus;
use App\Models\EventVolunteerCommitment;
use App\Models\EventVolunteerReminderDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventVolunteerReminderDeliveryFactory extends Factory
{
    protected $model = EventVolunteerReminderDelivery::class;

    public function definition(): array
    {
        $commitment = EventVolunteerCommitment::factory()->create();

        return ['event_volunteer_commitment_id' => $commitment->id, 'event_volunteer_position_id' => $commitment->event_volunteer_position_id, 'event_occurrence_id' => $commitment->event_occurrence_id, 'event_id' => $commitment->event_id, 'lodge_id' => $commitment->lodge_id, 'recipient_email' => $commitment->user->email, 'normalized_recipient_email' => mb_strtolower($commitment->user->email), 'due_at' => now(), 'status' => VolunteerReminderDeliveryStatus::Pending];
    }

    public function claimed(): static
    {
        return $this->state(['status' => VolunteerReminderDeliveryStatus::Claimed, 'claimed_at' => now()]);
    }

    public function sent(): static
    {
        return $this->state(['status' => VolunteerReminderDeliveryStatus::Sent, 'sent_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(['status' => VolunteerReminderDeliveryStatus::Failed, 'failed_at' => now(), 'last_error' => 'Test failure']);
    }
}
