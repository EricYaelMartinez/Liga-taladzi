<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVenueRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['latitude', 'longitude', 'contact_name', 'phone', 'notes'] as $field) {
            if ($this->input($field) === '') $this->merge([$field => null]);
        }
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,inactive'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
