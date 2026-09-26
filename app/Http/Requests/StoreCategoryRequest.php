<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['minimum_age', 'maximum_age', 'requirements'] as $field) {
            if ($this->input($field) === '') $this->merge([$field => null]);
        }
    }

    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'minimum_age' => ['nullable', 'integer', 'min:1', 'max:100'],
            'maximum_age' => ['nullable', 'integer', 'min:1', 'max:100'],
            'gender' => ['required', Rule::in(['mixed', 'male', 'female'])],
            'requirements' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $minimumAge = $this->input('minimum_age');
            $maximumAge = $this->input('maximum_age');
            if ($minimumAge !== null && $maximumAge !== null && (int) $maximumAge < (int) $minimumAge) {
                $validator->errors()->add('maximum_age', 'La edad máxima debe ser mayor o igual que la mínima.');
            }
        });
    }
}
