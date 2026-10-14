<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDisciplinePolicyRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'automatic_sanctions_enabled' => ['required', 'boolean'],
            'yellow_card_threshold' => ['required', 'integer', 'min:1', 'max:20'],
            'yellow_suspension_matches' => ['required', 'integer', 'min:1', 'max:20'],
            'second_yellow_suspension_matches' => ['required', 'integer', 'min:1', 'max:20'],
            'red_card_suspension_matches' => ['required', 'integer', 'min:1', 'max:50'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
