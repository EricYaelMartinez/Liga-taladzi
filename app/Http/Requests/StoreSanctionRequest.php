<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSanctionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'subject_type' => ['required', Rule::in(['player', 'team', 'staff'])],
            'player_id' => ['nullable', 'required_if:subject_type,player', 'integer', 'exists:players,id'],
            'team_id' => ['nullable', 'required_if:subject_type,team,staff', 'integer', 'exists:teams,id'],
            'subject_name' => ['nullable', 'required_if:subject_type,staff', 'string', 'max:180'],
            'kind' => ['required', Rule::in(['suspension', 'points_deduction', 'fine', 'warning', 'disqualification'])],
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
            'competition_id' => ['nullable', 'required_if:kind,points_deduction', 'integer', 'exists:competitions,id'],
            'match_id' => ['nullable', 'integer', 'exists:matches,id'],
            'matches_total' => ['nullable', 'required_if:kind,suspension', 'integer', 'min:1', 'max:100'],
            'points_deducted' => ['nullable', 'required_if:kind,points_deduction', 'integer', 'min:1', 'max:100'],
            'fine_amount' => ['nullable', 'required_if:kind,fine', 'numeric', 'min:0.01', 'max:999999999.99'],
            'reason' => ['required', 'string', 'max:3000'],
            'legal_basis' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
