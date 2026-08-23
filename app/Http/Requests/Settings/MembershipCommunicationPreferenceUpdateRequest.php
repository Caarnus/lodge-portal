<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class MembershipCommunicationPreferenceUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return ['receives_lodge_email' => ['required', 'boolean'], 'receives_print_newsletter' => ['sometimes', 'boolean']];
    }
}
