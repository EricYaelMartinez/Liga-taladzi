<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePlayerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['phone', 'email', 'guardian_name', 'guardian_phone', 'league_membership_id'] as $field) {
            if ($this->input($field) === '') $this->merge([$field => null]);
        }
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:180'],
            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female,unspecified'],
            'position' => ['required', 'in:goalkeeper,defender,midfielder,forward'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'emergency_contact_name' => ['required', 'string', 'max:180'],
            'emergency_contact_phone' => ['required', 'string', 'max:30'],
            'emergency_contact_relationship' => ['required', 'string', 'max:80'],
            'guardian_name' => ['nullable', 'string', 'max:180'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'guardian_consent' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'team_participation_id' => ['required', 'integer'],
            'jersey_number' => ['required', 'integer', 'between:0,999'],
            'league_membership_id' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('birth_date')) return;
            $minor = Carbon::parse($this->input('birth_date'))->age < 18;
            if ($minor && ! $this->filled('guardian_name')) $validator->errors()->add('guardian_name', 'El nombre del tutor es obligatorio para menores.');
            if ($minor && ! $this->filled('guardian_phone')) $validator->errors()->add('guardian_phone', 'El teléfono del tutor es obligatorio para menores.');
            if ($minor && ! $this->hasFile('guardian_consent')) $validator->errors()->add('guardian_consent', 'La carta responsiva es obligatoria para menores.');
        }];
    }
}
