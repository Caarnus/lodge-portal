<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class StoreLodgeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_platform_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'lodge_group_type_id' => ['required', 'integer', 'exists:lodge_group_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
            'has_public_landing_page' => ['required', 'boolean'],
        ];
    }
}
