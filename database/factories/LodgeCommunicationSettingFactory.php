<?php

namespace Database\Factories;

use App\Models\Lodge;
use App\Models\LodgeCommunicationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class LodgeCommunicationSettingFactory extends Factory
{
    protected $model = LodgeCommunicationSetting::class;

    public function definition(): array
    {
        return ['lodge_id' => Lodge::factory(), 'sender_display_name' => fake()->company(), 'reply_to_email' => fake()->safeEmail(), 'secretary_email' => fake()->safeEmail(), 'newsletter_contact_email' => fake()->safeEmail()];
    }
}
