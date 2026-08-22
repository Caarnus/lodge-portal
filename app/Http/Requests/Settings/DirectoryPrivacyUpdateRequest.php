<?php

namespace App\Http\Requests\Settings;

use App\Enums\DirectoryVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DirectoryPrivacyUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::enum(DirectoryVisibilityScope::class)],
            'show_email' => ['required', 'boolean'],
            'show_phone' => ['required', 'boolean'],
            'show_address' => ['required', 'boolean'],
            'show_profile_photo' => ['required', 'boolean'],
            'show_degree' => ['required', 'boolean'],
        ];
    }
}
