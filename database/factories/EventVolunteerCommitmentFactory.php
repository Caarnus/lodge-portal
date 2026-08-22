<?php

namespace Database\Factories;

use App\Enums\EventOccurrenceStatus;
use App\Enums\VolunteerCommitmentStatus;
use App\Models\EventOccurrence;
use App\Models\EventVolunteerCommitment;
use App\Models\EventVolunteerPosition;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventVolunteerCommitmentFactory extends Factory
{
    protected $model = EventVolunteerCommitment::class;

    public function definition(): array
    {
        $position = EventVolunteerPosition::factory()->create();
        $occurrence = EventOccurrence::create(['event_id' => $position->event_id, 'lodge_id' => $position->lodge_id, 'recurrence_key' => fake()->unique()->uuid(), 'original_starts_at' => now()->addWeek(), 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHour(), 'status' => EventOccurrenceStatus::Scheduled]);
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->id]);

        return ['event_volunteer_position_id' => $position->id, 'event_occurrence_id' => $occurrence->id, 'event_id' => $position->event_id, 'lodge_id' => $position->lodge_id, 'user_id' => $user->id, 'person_id' => $person->id, 'status' => VolunteerCommitmentStatus::Committed, 'committed_at' => now(), 'created_by' => $user->id];
    }

    public function withdrawn(): static
    {
        return $this->state(['status' => VolunteerCommitmentStatus::Withdrawn, 'withdrawn_at' => now()]);
    }

    public function administrativelyRemoved(): static
    {
        return $this->state(['status' => VolunteerCommitmentStatus::AdministrativelyRemoved, 'administratively_removed_at' => now()]);
    }
}
