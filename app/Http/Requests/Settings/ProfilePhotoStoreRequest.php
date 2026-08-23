<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ProfilePhotoStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return ['photo' => ['required', 'file', 'max:' . config('website.max_upload_kb')]];
    }
}
