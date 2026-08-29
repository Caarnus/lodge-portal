<?php
namespace App\Http\Requests;
use App\Enums\RitualVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class RitualSettingUpdateRequest extends FormRequest { public function rules(): array { return ['visibility_scope' => ['required', Rule::enum(RitualVisibilityScope::class)], 'public_availability_note' => ['nullable', 'string', 'max:500']]; } }
