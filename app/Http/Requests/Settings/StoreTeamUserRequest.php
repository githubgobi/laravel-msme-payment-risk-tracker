<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class StoreTeamUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->canManageUsers(); // Owner/Admin only
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'     => ['required', new Enum(UserRole::class)],
            'password' => ['required', Password::min(8)],
            'phone'    => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
        ];
    }
}
