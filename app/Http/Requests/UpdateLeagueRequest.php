<?php

namespace App\Http\Requests;

use App\Domain\League\Enums\LeagueStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('leagues.update') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:160', 'alpha_dash:ascii', Rule::unique('leagues', 'slug')->ignore($this->route('league'))],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::enum(LeagueStatus::class)],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
