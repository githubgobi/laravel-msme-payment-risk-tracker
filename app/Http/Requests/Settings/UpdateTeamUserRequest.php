<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTeamUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->canManageUsers(); // Owner/Admin only
    }

    public function rules(): array
    {
        return [
            'role'      => ['sometimes', new Enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
