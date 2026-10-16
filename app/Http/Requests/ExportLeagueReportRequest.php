<?php

namespace App\Http\Requests;

use App\Domain\Reporting\Services\LeagueReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportLeagueReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(LeagueReportService::TYPES)],
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'season_id' => ['nullable', 'integer'],
            'tournament_id' => ['nullable', 'integer'],
            'competition_id' => ['nullable', 'integer'],
            'division_id' => ['nullable', 'integer'],
            'matchday_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
            'player_id' => ['nullable', 'integer'],
            'referee_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
