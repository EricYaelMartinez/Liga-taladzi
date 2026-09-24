<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StoreLeagueMembershipRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower((string) $this->input('email'))]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', Rule::exists('users', 'email')->where('status', 'active')],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('scope', 'league')],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
