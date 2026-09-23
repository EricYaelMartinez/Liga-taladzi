<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.reset-password') === true;
    }

    public function rules(): array
    {
        return ['password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()]];
    }
}
