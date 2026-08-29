<?php

namespace App\Http\Requests;

use App\Enums\RitualDaypart;
use App\Enums\RitualProficiencyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LodgeRitualManagementUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'parts' => ['required', 'array'],
            'parts.*.ritual_part_id' => ['required', 'integer', 'distinct'],
            'parts.*.status' => ['required', Rule::enum(RitualProficiencyStatus::class)],
            'parts.*.interested_in_learning' => ['required', 'boolean'],
            'parts.*.willing_to_assist' => ['required', 'boolean'],
            'parts.*.performed_for_credit' => ['required', 'boolean'],
            'parts.*.confirm_performed_for_credit' => ['required', 'boolean'],
            'parts.*.first_marked_proficient_on' => ['nullable', 'date', 'before_or_equal:today'],
            'parts.*.notes' => ['prohibited'],
            'notes' => ['prohibited'],
            'windows' => ['required', 'array', 'max:21'],
            'windows.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'windows.*.daypart' => ['required', Rule::enum(RitualDaypart::class)],
        ];
    }
}
