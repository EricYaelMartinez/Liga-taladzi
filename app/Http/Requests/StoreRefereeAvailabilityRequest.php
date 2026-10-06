<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRefereeAvailabilityRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['valid_from', 'valid_until'] as $field) {
            if ($this->input($field) === '') $this->merge([$field => null]);
        }
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'weekday' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['valid_from', 'valid_until'])) return;
            if ($this->filled('valid_from') && $this->filled('valid_until') && $this->date('valid_until')->lt($this->date('valid_from'))) {
                $validator->errors()->add('valid_until', 'La fecha final debe ser igual o posterior a la fecha inicial.');
            }
        }];
    }
}
