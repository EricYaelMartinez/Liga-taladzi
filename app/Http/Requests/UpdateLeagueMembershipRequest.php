<?php

namespace App\Http\Requests;

use App\Domain\League\Enums\MembershipStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeagueMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(MembershipStatus::class)],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
