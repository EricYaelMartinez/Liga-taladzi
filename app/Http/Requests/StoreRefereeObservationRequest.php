<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRefereeObservationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'observed_on' => ['required', 'date'],
            'observation' => ['required', 'string', 'max:3000'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
