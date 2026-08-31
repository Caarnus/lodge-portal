<?php

namespace App\Http\Requests;

use App\Enums\RitualDaypart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RitualAvailabilityUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return ['windows' => ['present', 'array', 'max:21'], 'windows.*.day_of_week' => ['required', 'integer', 'between:1,7'], 'windows.*.daypart' => ['required', Rule::enum(RitualDaypart::class)], 'windows.*' => ['distinct:strict']];
    }
}
