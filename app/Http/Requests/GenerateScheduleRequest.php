<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateScheduleRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'first_match_date' => ['required', 'date'],
            'days_between_matchdays' => ['required', 'integer', 'between:1,30'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
