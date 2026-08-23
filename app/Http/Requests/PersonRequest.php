<?php

namespace App\Http\Requests;

use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => filled($this->email) ? strtolower(trim((string)$this->email)) : null,
            'phone' => $this->formatPhone($this->phone),
        ]);
    }

    public function rules(): array
    {
        $person = $this->route('person');
        $personId = $person instanceof Person ? $person->id : null;

        return [
            'legal_first_name' => 'required|string|max:100',
            'legal_middle_name' => 'nullable|string|max:100',
            'legal_last_name' => 'required|string|max:100',
            'legal_suffix' => 'nullable|string|max:32',
            'preferred_name' => 'nullable|string|max:100',
            'email' => array_values(array_filter([
                'nullable', 'email:rfc', 'max:255',
                $personId ? Rule::unique('people', 'email')->ignore($personId) : null,
            ])),
            'phone' => 'nullable|string|max:50',
            'mailing_address_line_1' => 'nullable|string|max:255',
            'mailing_address_line_2' => 'nullable|string|max:255',
            'mailing_city' => 'nullable|string|max:100',
            'mailing_state' => 'nullable|string|size:2',
            'mailing_postal_code' => 'nullable|string|max:16',
            'birth_date' => 'nullable|date|before_or_equal:today',
            'is_deceased' => 'required|boolean',
            'death_date' => 'nullable|date|before_or_equal:today|after_or_equal:birth_date',
        ];
    }

    public function personData(): array
    {
        $data = $this->safe()->only(array_keys($this->rules()));
        $data['name'] = collect([$data['legal_first_name'], $data['legal_middle_name'] ?? null, $data['legal_last_name'], $data['legal_suffix'] ?? null])->filter()->implode(' ');

        return $data;
    }

    private function formatPhone(mixed $phone): ?string
    {
        if (!filled($phone)) {
            return null;
        }

        $phone = trim((string)$phone);
        $digits = preg_replace('/\D/', '', $phone);
        if (!str_starts_with($phone, '+') && strlen($digits) === 10) {
            return sprintf('(%s)%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
        }
        if (str_starts_with($phone, '+') && strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return sprintf('+1 (%s)%s-%s', substr($digits, 1, 3), substr($digits, 4, 3), substr($digits, 7));
        }

        return $phone;
    }
}
