<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinanceChargeRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer'],
            'team_participation_id' => ['required', 'integer'],
            'season_id' => ['required', 'integer'],
            'concept_id' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'max:250'],
            'type' => ['required', Rule::in(['fee', 'bond', 'fine', 'other'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'issued_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:issued_on'],
        ];
    }
}
