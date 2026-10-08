<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleCapacityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'default_max_matches_per_field_day' => ['required', 'integer', 'between:1,50'],
            'fields' => ['present', 'array'],
            'fields.*.id' => ['required', 'integer', 'distinct', 'exists:playing_fields,id'],
            'fields.*.max_matches_per_day' => ['nullable', 'integer', 'between:1,50'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
