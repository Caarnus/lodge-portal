<?php

namespace Database\Factories;

use App\Models\Lodge;
use App\Models\NewsletterIssue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NewsletterIssueFactory extends Factory
{
    protected $model = NewsletterIssue::class;

    public function definition(): array
    {
        return ['lodge_id' => Lodge::factory(), 'slug' => Str::slug(fake()->unique()->sentence(3))];
    }
}
