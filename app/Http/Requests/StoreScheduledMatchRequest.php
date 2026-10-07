<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduledMatchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('public_notes') === '') $this->merge(['public_notes' => null]);
    }
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'home_team_participation_id' => ['required', 'integer', 'exists:team_participations,id', 'different:away_team_participation_id'],
            'away_team_participation_id' => ['required', 'integer', 'exists:team_participations,id', 'different:home_team_participation_id'],
            'public_notes' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
