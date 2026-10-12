<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmMatchReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['decision' => ['required', Rule::in(['confirmed', 'rejected'])], 'notes' => ['nullable', 'string', 'max:2000']]; }
}
