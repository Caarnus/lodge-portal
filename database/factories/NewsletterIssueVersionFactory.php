<?php

namespace Database\Factories;

use App\Enums\ContentVersionStatus;
use App\Models\NewsletterIssue;
use App\Models\NewsletterIssueVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class NewsletterIssueVersionFactory extends Factory
{
    protected $model = NewsletterIssueVersion::class;

    public function definition(): array
    {
        $issue = NewsletterIssue::factory()->create();

        return [
            'lodge_id' => $issue->lodge_id,
            'newsletter_issue_id' => $issue->id,
            'status' => ContentVersionStatus::Draft,
            'title' => fake()->sentence(4),
            'publication_date' => today(),
            'body_html' => '<p>'.e(fake()->paragraph()).'</p>',
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => ContentVersionStatus::Published, 'published_at' => now()]);
    }
}
