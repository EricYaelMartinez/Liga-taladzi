<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlayingFieldRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['capacity', 'notes'] as $field) if ($this->input($field) === '') $this->merge([$field => null]);
        $this->merge(['has_lighting' => $this->boolean('has_lighting')]);
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'surface' => ['required', 'in:natural_grass,synthetic,dirt,concrete,other'],
            'has_lighting' => ['required', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,maintenance,inactive'],
            'division_ids' => ['array'],
            'division_ids.*' => ['integer', 'distinct'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
