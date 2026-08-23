<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_type_id' => ['nullable', 'integer', Rule::exists('membership_types', 'id')->where('is_active', true)],
            'membership_status_id' => ['required', 'integer', Rule::exists('membership_statuses', 'id')->where('is_active', true)],
            'masonic_degree_id' => ['nullable', 'integer', Rule::exists('masonic_degrees', 'id')->where('is_active', true)],
            'primary_lodge_number' => 'nullable|string|max:50',
            'member_number' => 'nullable|string|max:100',
            'is_award_of_gold' => 'boolean',
            'entered_apprentice_date' => 'nullable|date|before_or_equal:today',
            'fellow_craft_date' => 'nullable|date|before_or_equal:today',
            'master_mason_date' => 'nullable|date|before_or_equal:today',
            'affiliation_date' => 'nullable|date|before_or_equal:today',
            'demit_withdrawal_date' => 'nullable|date|before_or_equal:today',
            'end_date' => 'nullable|date|before_or_equal:today',
            'notes' => 'nullable|string|max:10000',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $dates = collect(['entered_apprentice_date', 'fellow_craft_date', 'master_mason_date'])
                ->map(fn($field) => [$field, $this->date($field)?->getTimestamp()])->filter(fn($item) => $item[1] !== null)->values();
            for ($index = 1; $index < $dates->count(); $index++) {
                if ($dates[$index][1] < $dates[$index - 1][1]) {
                    $validator->errors()->add($dates[$index][0], 'Degree dates must be in chronological order.');
                }
            }
        }];
    }
}
