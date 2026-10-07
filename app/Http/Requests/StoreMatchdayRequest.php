<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMatchdayRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['starts_on', 'ends_on'] as $field) if ($this->input($field) === '') $this->merge([$field => null]);
    }
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'competition_id' => ['required', 'integer', 'exists:competitions,id'],
            'number' => ['required', 'integer', 'between:1,999'],
            'name' => ['required', 'string', 'max:120'],
            'phase' => ['required', 'in:regular,group,knockout,custom'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->hasAny(['starts_on', 'ends_on']) && $this->filled('starts_on') && $this->filled('ends_on') && $this->date('ends_on')->lt($this->date('starts_on'))) {
                $validator->errors()->add('ends_on', 'La fecha final debe ser igual o posterior a la inicial.');
            }
        }];
    }
}
