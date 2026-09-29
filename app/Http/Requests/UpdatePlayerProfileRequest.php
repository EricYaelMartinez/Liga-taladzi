<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlayerProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['phone', 'email'] as $field) if ($this->input($field) === '') $this->merge([$field => null]);
    }
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'emergency_contact_name' => ['required', 'string', 'max:180'],
            'emergency_contact_phone' => ['required', 'string', 'max:30'],
            'emergency_contact_relationship' => ['required', 'string', 'max:80'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
