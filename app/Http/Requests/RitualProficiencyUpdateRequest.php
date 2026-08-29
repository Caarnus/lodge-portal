<?php
namespace App\Http\Requests;
use App\Enums\RitualProficiencyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class RitualProficiencyUpdateRequest extends FormRequest { public function rules(): array { return ['status' => ['sometimes', Rule::enum(RitualProficiencyStatus::class)], 'interested_in_learning' => ['sometimes', 'boolean'], 'willing_to_assist' => ['sometimes', 'boolean'], 'performed_for_credit' => ['sometimes', 'boolean'], 'confirm_performed_for_credit' => ['sometimes', 'boolean'], 'first_marked_proficient_on' => ['nullable', 'date', 'before_or_equal:today'], 'notes' => ['nullable', 'string', 'max:2000']]; } }
