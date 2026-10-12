<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMatchReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'home_score' => ['required', 'integer', 'min:0', 'max:999'],
            'away_score' => ['required', 'integer', 'min:0', 'max:999'],
            'home_no_show' => ['required', 'boolean'],
            'away_no_show' => ['required', 'boolean'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'incidents' => ['nullable', 'string', 'max:10000'],
            'events' => ['present', 'array', 'max:200'],
            'events.*.team_participation_id' => ['nullable', 'integer'],
            'events.*.player_registration_id' => ['nullable', 'integer'],
            'events.*.related_player_registration_id' => ['nullable', 'integer'],
            'events.*.type' => ['required', Rule::in(['goal', 'own_goal', 'yellow_card', 'second_yellow', 'red_card', 'substitution', 'injury', 'incident'])],
            'events.*.minute' => ['nullable', 'integer', 'min:0', 'max:300'],
            'events.*.period' => ['required', Rule::in(['first_half', 'halftime', 'second_half', 'extra_time', 'penalties', 'post_match', 'match'])],
            'events.*.notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
