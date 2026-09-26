<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperationalSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['halftime_minutes', 'schedule_buffer_minutes', 'payment_grace_days', 'bond_amount'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'match_periods' => ['required', 'integer', 'min:1', 'max:4'],
            'period_duration_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'halftime_minutes' => ['nullable', 'integer', 'min:0', 'max:60'],
            'schedule_buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'appeal_deadline_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'payment_grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'reactivation_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'bond_enabled' => ['required', 'boolean'],
            'bond_amount' => ['nullable', Rule::requiredIf($this->boolean('bond_enabled')), 'numeric', 'min:0', 'max:9999999.99'],
            'currency' => ['required', Rule::in(['MXN'])],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
