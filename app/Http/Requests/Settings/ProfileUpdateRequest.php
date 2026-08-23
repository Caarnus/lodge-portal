<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mailing_address_line_1' => ['nullable', 'string', 'max:255'],
            'mailing_address_line_2' => ['nullable', 'string', 'max:255'],
            'mailing_city' => ['nullable', 'string', 'max:100'],
            'mailing_state' => ['nullable', 'string', 'size:2'],
            'mailing_postal_code' => ['nullable', 'string', 'max:16'],
            'name' => ['prohibited'],
            'person_id' => ['prohibited'],
            'approval_status' => ['prohibited'],
            'current_lodge_id' => ['prohibited'],
            'legal_first_name' => ['prohibited'],
            'legal_middle_name' => ['prohibited'],
            'legal_last_name' => ['prohibited'],
            'legal_suffix' => ['prohibited'],
            'birth_date' => ['prohibited'],
            'death_date' => ['prohibited'],
            'is_deceased' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'preferred_name' => $this->trimmedOrNull($this->preferred_name),
            'email' => strtolower((string)$this->trimmedOrNull($this->email)),
            'phone' => $this->formatPhone($this->phone),
            'mailing_address_line_1' => $this->trimmedOrNull($this->mailing_address_line_1),
            'mailing_address_line_2' => $this->trimmedOrNull($this->mailing_address_line_2),
            'mailing_city' => $this->trimmedOrNull($this->mailing_city),
            'mailing_state' => strtoupper((string)$this->trimmedOrNull($this->mailing_state)),
            'mailing_postal_code' => $this->trimmedOrNull($this->mailing_postal_code),
        ]);
    }

    private function trimmedOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return filled($value) ? $value : null;
    }

    private function formatPhone(mixed $phone): ?string
    {
        $phone = $this->trimmedOrNull($phone);
        if (!$phone) {
            return null;
        }

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
