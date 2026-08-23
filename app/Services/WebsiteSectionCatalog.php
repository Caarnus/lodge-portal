<?php

namespace App\Services;

use App\Models\Lodge;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WebsiteSectionCatalog
{
    public const TYPES = [
        'hero', 'rich_text', 'image', 'image_text', 'link_list', 'call_to_action',
        'meeting_information', 'contact_information', 'officers_placeholder',
        'past_masters_placeholder', 'events_placeholder', 'newsletter_placeholder',
        'directory_placeholder', 'gallery_placeholder', 'custom_html',
    ];

    public function __construct(private readonly WebsiteHtmlSanitizer $sanitizer)
    {
    }

    public function labels(): array
    {
        return [
            'hero' => 'Hero', 'rich_text' => 'Rich text', 'image' => 'Image',
            'image_text' => 'Image with text', 'link_list' => 'Link list',
            'call_to_action' => 'Call to action', 'meeting_information' => 'Meeting information',
            'contact_information' => 'Contact information', 'officers_placeholder' => 'Officers',
            'past_masters_placeholder' => 'Past Masters',
            'events_placeholder' => 'Upcoming events', 'newsletter_placeholder' => 'Newsletter',
            'directory_placeholder' => 'Member directory',
            'gallery_placeholder' => 'Gallery', 'custom_html' => 'Custom HTML',
        ];
    }

    public function defaultConfiguration(string $type, bool $platformAdmin): array
    {
        if (!in_array($type, self::TYPES, true) || ($type === 'custom_html' && !$platformAdmin)) {
            throw ValidationException::withMessages(['type' => 'This section type is not available.']);
        }

        return match ($type) {
            'hero' => ['heading' => '', 'body' => '', 'media_id' => null],
            'rich_text', 'custom_html' => ['html' => '<p></p>'],
            'image' => ['media_id' => null, 'caption' => ''],
            'image_text' => ['media_id' => null, 'heading' => '', 'body' => '', 'image_side' => 'left'],
            'link_list' => ['heading' => '', 'links' => []],
            'call_to_action' => ['heading' => '', 'body' => '', 'label' => '', 'url' => ''],
            'meeting_information' => ['heading' => 'Meeting Information', 'body' => ''],
            'contact_information' => ['heading' => 'Contact Us', 'body' => '', 'show_contact_form' => false],
            'officers_placeholder' => ['heading' => 'Lodge Officers', 'body' => 'Officer information will be available soon.'],
            'past_masters_placeholder' => ['heading' => 'Past Masters', 'body' => 'Our lodge is grateful for the service of these Past Masters.'],
            'events_placeholder' => ['heading' => 'Upcoming Events', 'body' => 'Event listings are coming soon.', 'event_category_id' => null, 'maximum_items' => 6, 'show_all_link' => true],
            'newsletter_placeholder' => ['heading' => 'Newsletter', 'body' => 'Newsletters will be available soon.'],
            'directory_placeholder' => ['heading' => 'Member Directory', 'body' => 'Search the member directory.'],
            'gallery_placeholder' => ['heading' => 'Gallery', 'body' => 'Photos will be available soon.'],
        };
    }

    public function validate(string $type, array $input, Lodge $lodge, bool $platformAdmin): array
    {
        if (!in_array($type, self::TYPES, true) || ($type === 'custom_html' && !$platformAdmin)) {
            throw ValidationException::withMessages(['type' => 'This section type is not available.']);
        }

        $rules = match ($type) {
            'hero' => ['heading' => 'required|string|max:150', 'body' => 'nullable|string|max:1000', 'media_id' => 'nullable|integer'],
            'rich_text', 'custom_html' => ['html' => 'required|string|max:100000'],
            'image' => ['media_id' => 'required|integer', 'caption' => 'nullable|string|max:500'],
            'image_text' => ['media_id' => 'required|integer', 'heading' => 'required|string|max:150', 'body' => 'nullable|string|max:5000', 'image_side' => ['required', Rule::in(['left', 'right'])]],
            'link_list' => ['heading' => 'nullable|string|max:150', 'links' => 'required|array|max:20', 'links.*.label' => 'required|string|max:100', 'links.*.url' => ['required', 'string', 'max:2048', $this->safeUrlRule()]],
            'call_to_action' => ['heading' => 'required|string|max:150', 'body' => 'nullable|string|max:1000', 'label' => 'required|string|max:100', 'url' => ['required', 'string', 'max:2048', $this->safeUrlRule()]],
            'events_placeholder' => ['heading' => 'nullable|string|max:150', 'body' => 'nullable|string|max:1000', 'event_category_id' => ['nullable', 'integer', Rule::exists('event_category_lodge', 'event_category_id')->where('lodge_id', $lodge->id)], 'maximum_items' => ['nullable', 'integer', 'min:1', 'max:20'], 'show_all_link' => ['nullable', 'boolean']],
            'contact_information' => ['heading' => 'nullable|string|max:150', 'body' => 'nullable|string|max:1000', 'show_contact_form' => ['nullable', 'boolean']],
            'meeting_information', 'officers_placeholder', 'past_masters_placeholder', 'newsletter_placeholder', 'directory_placeholder', 'gallery_placeholder' => ['heading' => 'nullable|string|max:150', 'body' => 'nullable|string|max:1000'],
        };

        $data = Validator::make($input, $rules)->validate();
        foreach ($this->mediaIds($data) as $mediaId) {
            $valid = MediaAsset::query()->whereKey($mediaId)->where('processing_status', 'ready')
                ->where('visibility', 'public')
                ->where(fn($query) => $query->where('lodge_id', $lodge->id)->orWhere('is_platform_shared', true))->exists();
            if (!$valid) {
                throw ValidationException::withMessages(['configuration.media_id' => 'Selected media is unavailable or still processing.']);
            }
        }

        if (isset($data['html'])) {
            $data['html'] = $this->sanitizer->sanitize($data['html']);
        }

        return $data;
    }

    public function mediaIds(array $configuration): array
    {
        $ids = [];
        array_walk_recursive($configuration, function ($value, $key) use (&$ids) {
            if (($key === 'media_id' || str_ends_with((string)$key, '_media_id')) && is_numeric($value)) {
                $ids[] = (int)$value;
            }
        });

        return array_values(array_unique($ids));
    }

    private function safeUrlRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (!preg_match('~^(?:/(?!/)|https?://|mailto:|tel:)~i', (string)$value)) {
                $fail("The {$attribute} must be a lodge-relative, HTTP(S), email, or telephone link.");
            }
        };
    }
}
