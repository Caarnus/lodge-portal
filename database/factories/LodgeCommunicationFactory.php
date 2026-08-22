<?php

namespace Database\Factories;

use App\Enums\LodgeCommunicationStatus;
use App\Models\Lodge;
use App\Models\LodgeCommunication;
use Illuminate\Database\Eloquent\Factories\Factory;

class LodgeCommunicationFactory extends Factory
{
    protected $model = LodgeCommunication::class;

    public function definition(): array
    {
        return ['lodge_id' => Lodge::factory(), 'status' => LodgeCommunicationStatus::Draft, 'subject' => fake()->sentence(5), 'body_html' => '<p>'.e(fake()->paragraph()).'</p>'];
    }
}
