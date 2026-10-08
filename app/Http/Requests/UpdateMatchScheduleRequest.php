<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchScheduleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('public_notes') === '') $this->merge(['public_notes' => null]);
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'playing_field_id' => ['required', 'integer', 'exists:playing_fields,id'],
            'public_notes' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
