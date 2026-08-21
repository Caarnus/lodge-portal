<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfficerAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_id' => ['required', 'integer'],
            'officer_position_id' => ['required', 'integer', Rule::exists('officer_positions', 'id')->where('is_active', true)],
            'is_public' => 'required|boolean',
            'show_email' => 'required|boolean',
            'show_phone' => 'required|boolean',
        ];
    }
}
