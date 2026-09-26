<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionTeamParticipationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'reject', 'suspend', 'reactivate', 'deactivate', 'deregister'])],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
