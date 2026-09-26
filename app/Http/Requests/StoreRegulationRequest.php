<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegulationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'points_win' => ['required', 'integer', 'min:-20', 'max:20'],
            'points_draw' => ['required', 'integer', 'min:-20', 'max:20'],
            'points_loss' => ['required', 'integer', 'min:-20', 'max:20'],
            'walkover_home_goals' => ['required', 'integer', 'min:0', 'max:99'],
            'walkover_away_goals' => ['required', 'integer', 'min:0', 'max:99'],
            'fair_play_yellow_points' => ['required', 'integer', 'min:0', 'max:20'],
            'fair_play_second_yellow_points' => ['required', 'integer', 'min:0', 'max:20'],
            'fair_play_red_points' => ['required', 'integer', 'min:0', 'max:20'],
            'tiebreakers' => ['required', 'array', 'min:1', 'max:7'],
            'tiebreakers.*' => ['required', 'distinct', Rule::in([
                'points', 'goal_difference', 'goals_for', 'head_to_head', 'fair_play',
                'administrative_decision', 'draw_lots',
            ])],
        ];
    }
}
