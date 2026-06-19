<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public route — no auth required
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'min:2', 'max:200'],
            'name'          => ['required', 'string', 'min:2', 'max:100'],
            'email'         => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'phone'         => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'gstin'         => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', 'unique:tenants,gstin'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'    => 'An account with this email already exists.',
            'gstin.regex'     => 'GSTIN must be a valid 15-character format (e.g. 29ABCDE1234F1Z5).',
            'gstin.unique'    => 'This GSTIN is already registered.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
