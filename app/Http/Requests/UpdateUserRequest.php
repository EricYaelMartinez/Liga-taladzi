<?php

namespace App\Http\Requests;

use App\Domain\Identity\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower((string) $this->input('email'))]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.update') === true;
    }

    public function rules(): array
    {
        $managedUser = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($managedUser)],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'role_id' => ['nullable', Rule::exists('roles', 'id')->where('scope', 'system')],
        ];
    }
}
