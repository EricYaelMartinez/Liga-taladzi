<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlayerRegistrationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'team_participation_id' => ['required', 'integer'],
            'jersey_number' => ['required', 'integer', 'between:0,999'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
