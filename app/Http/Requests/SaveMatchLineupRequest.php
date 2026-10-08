<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMatchLineupRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'players' => ['required', 'array', 'min:1', 'max:100'],
            'players.*.player_registration_id' => ['required', 'integer', 'distinct'],
            'players.*.role' => ['required', Rule::in(['starter', 'substitute'])],
            'players.*.is_captain' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'players.min' => 'Selecciona al menos un jugador.',
            'players.*.player_registration_id.distinct' => 'Un jugador no puede aparecer dos veces.',
        ];
    }
}
