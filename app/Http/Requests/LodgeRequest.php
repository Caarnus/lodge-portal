<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LodgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool)$this->user();
    }

    public function rules(): array
    {
        $id = $this->route('lodge')?->id;

        return [
            'name' => 'required|string|max:255',
            'number' => 'required|string|max:30',
            'slug' => [
                'required', 'alpha_dash',
                'max:100', Rule::unique('lodges')->ignore($id)
            ],
            'city' => 'required|string|max:100',
            'state' => 'required|string|size:2',
            'jurisdiction' => 'required|string|max:100',
            'physical_address' => 'required|string|max:255',
            'mailing_address' => 'nullable|string|max:255',
            'meeting_location' => 'nullable|string|max:255',
            'timezone' => 'required|timezone',
            'public_email' => 'required|email',
            'public_phone' => 'nullable|string|max:40',
            'status' => ['required', Rule::in(['active', 'disabled', 'disabled_locked'])],
            'date_display_format' => ['required', Rule::in(['month_year', 'month_day_year', 'day_month_year'])],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => 'nullable|image|max:2048'];
    }
}
