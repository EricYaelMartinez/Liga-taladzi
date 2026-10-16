<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'general_enabled' => ['required', 'boolean'],
            'schedule_enabled' => ['required', 'boolean'],
            'finance_enabled' => ['required', 'boolean'],
            'discipline_enabled' => ['required', 'boolean'],
        ];
    }
}
