<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeagueNoticeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'category' => ['required', Rule::in(['general', 'schedule', 'finance', 'discipline'])],
            'priority' => ['required', Rule::in(['normal', 'important', 'urgent'])],
            'audience_type' => ['required', Rule::in(['all', 'roles', 'teams'])],
            'audience_role_slugs' => ['exclude_unless:audience_type,roles', 'required_if:audience_type,roles', 'array', 'min:1'],
            'audience_role_slugs.*' => ['string', Rule::in(['league_admin', 'referee', 'team_representative', 'player'])],
            'audience_team_ids' => ['exclude_unless:audience_type,teams', 'required_if:audience_type,teams', 'array', 'min:1'],
            'audience_team_ids.*' => ['integer', 'distinct'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'audience_role_slugs.required_if' => 'Selecciona al menos un rol destinatario.',
            'audience_team_ids.required_if' => 'Selecciona al menos un equipo destinatario.',
            'expires_at.after' => 'La fecha de vencimiento debe ser posterior al momento actual.',
        ];
    }
}
