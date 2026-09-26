<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'season_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
        ];
    }
}
