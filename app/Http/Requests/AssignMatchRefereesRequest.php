<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignMatchRefereesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['assistant_1_referee_id', 'assistant_2_referee_id', 'fourth_referee_id'] as $field) {
            if ($this->input($field) === '') $this->merge([$field => null]);
        }
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'central_referee_id' => ['required', 'integer', 'exists:referees,id'],
            'assistant_1_referee_id' => ['nullable', 'integer', 'exists:referees,id', 'different:central_referee_id'],
            'assistant_2_referee_id' => ['nullable', 'integer', 'exists:referees,id', 'different:central_referee_id', 'different:assistant_1_referee_id'],
            'fourth_referee_id' => ['nullable', 'integer', 'exists:referees,id', 'different:central_referee_id', 'different:assistant_1_referee_id', 'different:assistant_2_referee_id'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
