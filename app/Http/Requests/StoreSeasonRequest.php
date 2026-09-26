<?php

namespace App\Http\Requests;

use App\Domain\Competition\Enums\SeasonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeasonRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'registration_starts_at' => ['nullable', 'required_with:registration_ends_at', 'date'],
            'registration_ends_at' => ['nullable', 'date', 'after_or_equal:registration_starts_at'],
            'status' => ['required', Rule::enum(SeasonStatus::class)->only([
                SeasonStatus::Planning, SeasonStatus::Registration, SeasonStatus::Active,
            ])],
        ];
    }
}
