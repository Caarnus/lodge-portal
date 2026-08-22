<?php

namespace Database\Factories;

use App\Enums\DistributionRunStatus;
use App\Models\CommunicationDistributionRun;
use App\Models\NewsletterIssue;
use App\Models\NewsletterIssueVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CommunicationDistributionRunFactory extends Factory
{
    protected $model = CommunicationDistributionRun::class;

    public function definition(): array
    {
        $issue = NewsletterIssue::factory()->create();
        $version = NewsletterIssueVersion::factory()->create([
            'lodge_id' => $issue->lodge_id,
            'newsletter_issue_id' => $issue->id,
        ]);

        return [
            'lodge_id' => $issue->lodge_id,
            'kind' => 'newsletter',
            'newsletter_issue_version_id' => $version->id,
            'status' => DistributionRunStatus::Preparing,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
