<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventVolunteerPosition;
use App\Models\Lodge;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventVolunteerPositionFactory extends Factory
{
    protected $model = EventVolunteerPosition::class;

    public function definition(): array
    {
        $lodge = Lodge::factory()->create();
        $event = Event::create(['lodge_id' => $lodge->id, 'slug' => fake()->unique()->slug(), 'status' => EventStatus::Draft, 'title' => fake()->sentence(3), 'time_zone' => $lodge->timezone, 'first_starts_at' => now()->addWeek(), 'duration_minutes' => 60, 'visibility' => EventVisibility::Public]);

        return ['event_id' => $event->id, 'lodge_id' => $lodge->id, 'name' => fake()->words(2, true), 'description' => fake()->sentence(), 'needed_count' => fake()->numberBetween(1, 5), 'sort_order' => 0, 'is_active' => true];
    }

    public function occurrence(int $occurrenceId): static
    {
        return $this->state(['event_occurrence_id' => $occurrenceId]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
