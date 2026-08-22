<?php

namespace Database\Factories;

use App\Models\Lodge;
use App\Models\NewsletterDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class NewsletterDocumentFactory extends Factory
{
    protected $model = NewsletterDocument::class;

    public function definition(): array
    {
        return [
            'lodge_id' => Lodge::factory(),
            'original_name' => 'newsletter.pdf',
            'storage_path' => 'newsletter-documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'sha256' => hash('sha256', fake()->uuid()),
        ];
    }
}
