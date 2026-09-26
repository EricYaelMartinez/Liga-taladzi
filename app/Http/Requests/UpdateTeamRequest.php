<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['phone', 'email', 'founded_on', 'description'] as $field) {
            if ($this->input($field) === '') $this->merge([$field => null]);
        }
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['required', 'string', 'max:30'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'founded_on' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
            'crest' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'team_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
