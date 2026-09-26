<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamParticipationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'competition_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
