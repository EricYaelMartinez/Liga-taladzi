<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinanceConceptRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['fee', 'bond', 'fine', 'other'])],
            'default_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
        ];
    }
}
