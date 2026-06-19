<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->canManageUsers(); // Owner/Admin only
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'name'          => ['required', 'string', 'min:2', 'max:200'],
            'gstin'         => [
                'nullable', 'string',
                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/',
                Rule::unique('tenants', 'gstin')->ignore($tenantId),
            ],
            'pan'           => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:50'],
            'city'          => ['nullable', 'string', 'max:100'],
            'address'       => ['nullable', 'string', 'max:500'],
            'phone'         => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'email'         => ['nullable', 'email', 'max:255'],
            'rbi_bank_rate' => ['nullable', 'numeric', 'min:1', 'max:25'],
        ];
    }

    public function messages(): array
    {
        return [
            'gstin.regex' => 'GSTIN must be a valid 15-character format.',
            'pan.regex'   => 'PAN must be a valid 10-character format (e.g. ABCDE1234F).',
        ];
    }
}
