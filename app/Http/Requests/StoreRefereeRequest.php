<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRefereeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['contact_email', 'contact_phone', 'joined_on', 'notes'] as $field) {
            if ($this->input($field) === '') $this->merge([$field => null]);
        }
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'category_level' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
            'joined_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
