<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewMatchReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['action' => ['required', Rule::in(['approve', 'return'])], 'notes' => ['required', 'string', 'max:3000']]; }
}
