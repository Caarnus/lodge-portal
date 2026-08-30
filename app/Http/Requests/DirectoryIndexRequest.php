<?php

namespace App\Http\Requests;

use App\Enums\DirectoryAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DirectoryIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'audience' => ['nullable', Rule::enum(DirectoryAudience::class)],
            'query' => ['nullable', 'string', 'max:100'],
            'degree' => ['nullable', 'integer', 'exists:masonic_degrees,id'],
            'group' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
