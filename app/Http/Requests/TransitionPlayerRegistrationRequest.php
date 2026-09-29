<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransitionPlayerRegistrationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approve,reject,suspend,reactivate,release'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
