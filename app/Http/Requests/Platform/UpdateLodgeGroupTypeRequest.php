<?php

namespace App\Http\Requests\Platform;

class UpdateLodgeGroupTypeRequest extends StoreLodgeGroupTypeRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), ['is_active' => ['required', 'boolean']]);
    }
}
