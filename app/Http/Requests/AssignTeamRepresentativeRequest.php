<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignTeamRepresentativeRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'league_membership_id' => ['required', 'integer'],
            'representative_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'representative_ine' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
