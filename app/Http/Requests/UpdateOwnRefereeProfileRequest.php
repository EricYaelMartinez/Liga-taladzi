<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnRefereeProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['contact_email', 'contact_phone'] as $field) {
            if ($this->input($field) === '') $this->merge([$field => null]);
        }
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
