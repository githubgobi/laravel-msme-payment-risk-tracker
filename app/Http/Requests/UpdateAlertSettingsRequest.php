<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlertSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'email_enabled'      => ['boolean'],
            'whatsapp_enabled'   => ['boolean'],
            'email_recipients'   => ['array', 'max:10'],
            'email_recipients.*' => ['required', 'email', 'max:255'],
            'whatsapp_number'    => ['nullable', 'string', 'regex:/^\+?[1-9]\d{9,14}$/'],
            't10_enabled'        => ['boolean'],
            't3_enabled'         => ['boolean'],
            'overdue_enabled'    => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email_recipients.*.email'  => 'Each recipient must be a valid email address.',
            'whatsapp_number.regex'     => 'WhatsApp number must be in E.164 format (e.g. +919876543210).',
        ];
    }
}
