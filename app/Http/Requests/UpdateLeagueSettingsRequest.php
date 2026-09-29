<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeagueSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'credential_logo_1' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'credential_logo_2' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'credential_logo_3' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'credential_logo_4' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_credential_logo_1' => ['sometimes', 'boolean'],
            'remove_credential_logo_2' => ['sometimes', 'boolean'],
            'remove_credential_logo_3' => ['sometimes', 'boolean'],
            'remove_credential_logo_4' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
