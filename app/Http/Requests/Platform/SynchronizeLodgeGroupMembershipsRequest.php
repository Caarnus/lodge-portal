<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class SynchronizeLodgeGroupMembershipsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_platform_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'lodge_ids' => ['present', 'array', 'max:500'],
            'lodge_ids.*' => ['integer', 'distinct', 'exists:lodges,id'],
        ];
    }
}
