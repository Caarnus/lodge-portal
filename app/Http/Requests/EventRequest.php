<?php

namespace App\Http\Requests;

use App\Enums\EventQualification;
use App\Enums\EventVisibility;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Event|null $event */
        $event = $this->route('event');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('events')->where('lodge_id', $this->route('lodge')?->id)->ignore($event?->id)],
            'description' => ['nullable', 'string'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'location_details' => ['nullable', 'string'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'time_zone' => ['required', 'timezone'],
            'first_starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'rrule' => ['nullable', 'string', 'max:2000'],
            'event_category_id' => ['nullable', 'integer', Rule::exists('event_category_lodge', 'event_category_id')->where('lodge_id', $this->route('lodge')?->id)],
            'visibility' => ['required', Rule::enum(EventVisibility::class)],
            'required_qualification' => ['nullable', Rule::enum(EventQualification::class)],
            'allows_cross_lodge_reservations' => ['boolean'],
            'reservations_enabled' => ['boolean'],
            'guest_reservations_enabled' => ['boolean'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'maximum_party_size' => ['nullable', 'integer', 'min:1'],
            'reminders_enabled' => ['boolean'],
            'guest_reminders_enabled' => ['boolean'],
            'cover_media_asset_id' => ['nullable', 'integer'],
            'confirm_schedule_change' => ['boolean'],
        ];
    }
}
