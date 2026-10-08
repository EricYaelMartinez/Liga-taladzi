<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleTimeSlotRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('playing_field_id') === '' || $this->input('playing_field_id') === 0) {
            $this->merge(['playing_field_id' => null]);
        }
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'playing_field_id' => ['nullable', 'integer', 'exists:playing_fields,id'],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
