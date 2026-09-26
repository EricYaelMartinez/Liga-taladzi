<?php

namespace App\Http\Requests;

use App\Domain\Competition\Enums\CompetitionFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCompetitionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['minimum_teams', 'maximum_teams', 'registration_starts_at', 'registration_ends_at'] as $field) {
            if ($this->input($field) === '') $this->merge([$field => null]);
        }
    }

    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'tournament_id' => ['required', 'integer'],
            'division_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'regulation_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'format' => ['required', Rule::enum(CompetitionFormat::class)],
            'regular_leg_count' => ['required', 'integer', 'min:1', 'max:4'],
            'knockout_leg_count' => ['required', 'integer', 'min:1', 'max:2'],
            'minimum_teams' => ['nullable', 'integer', 'min:2', 'max:200'],
            'maximum_teams' => ['nullable', 'integer', 'min:2', 'max:200'],
            'minimum_roster_size' => ['required', 'integer', 'min:1', 'max:100'],
            'maximum_roster_size' => ['required', 'integer', 'min:1', 'max:100'],
            'registration_starts_at' => ['nullable', 'required_with:registration_ends_at', 'date'],
            'registration_ends_at' => ['nullable', 'date', 'after_or_equal:registration_starts_at'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $minimumTeams = $this->input('minimum_teams');
            $maximumTeams = $this->input('maximum_teams');
            if ($minimumTeams !== null && $maximumTeams !== null && (int) $maximumTeams < (int) $minimumTeams) {
                $validator->errors()->add('maximum_teams', 'El máximo de equipos debe ser mayor o igual que el mínimo.');
            }

            if ((int) $this->input('maximum_roster_size') < (int) $this->input('minimum_roster_size')) {
                $validator->errors()->add('maximum_roster_size', 'El máximo de jugadores debe ser mayor o igual que el mínimo.');
            }
        });
    }
}
