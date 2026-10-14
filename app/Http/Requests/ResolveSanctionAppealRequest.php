<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveSanctionAppealRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['uphold', 'reduce', 'revoke'])],
            'matches_total' => ['nullable', Rule::requiredIf(fn () => $this->input('action') === 'reduce' && $this->route('appeal')?->sanction?->kind === 'suspension'), 'integer', 'min:0', 'max:100'],
            'points_deducted' => ['nullable', Rule::requiredIf(fn () => $this->input('action') === 'reduce' && $this->route('appeal')?->sanction?->kind === 'points_deduction'), 'integer', 'min:0', 'max:100'],
            'fine_amount' => ['nullable', Rule::requiredIf(fn () => $this->input('action') === 'reduce' && $this->route('appeal')?->sanction?->kind === 'fine'), 'numeric', 'min:0.01', 'max:999999999.99'],
            'resolution' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
