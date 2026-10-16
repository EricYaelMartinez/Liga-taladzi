<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkFinanceChargeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'team_participation_ids' => ['required', 'array', 'min:1', 'max:500'],
            'team_participation_ids.*' => ['required', 'integer', 'distinct'],
            'concept_id' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'max:250'],
            'type' => ['required', Rule::in(['fee', 'bond', 'fine', 'other'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'issued_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:issued_on'],
        ];
    }

    public function messages(): array
    {
        return [
            'team_participation_ids.required' => 'Selecciona al menos un equipo.',
            'team_participation_ids.min' => 'Selecciona al menos un equipo.',
            'team_participation_ids.max' => 'Puedes asignar el cobro a un máximo de 500 equipos por operación.',
        ];
    }
}
