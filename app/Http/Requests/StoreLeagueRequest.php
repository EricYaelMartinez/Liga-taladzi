<?php

namespace App\Http\Requests;

use App\Domain\League\Enums\LeagueStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('leagues.create') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:160', 'alpha_dash:ascii', 'unique:leagues,slug'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::enum(LeagueStatus::class)],
            'initial_admin_user_id' => ['required', Rule::exists('users', 'id')->where('status', 'active')],
        ];
    }
}
