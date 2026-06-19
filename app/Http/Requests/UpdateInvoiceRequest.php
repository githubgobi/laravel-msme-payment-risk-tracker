<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canManageInvoices();
    }

    public function rules(): array
    {
        return [
            'narration'       => ['nullable', 'string', 'max:1000'],
            'agreement_exists' => ['sometimes', 'boolean'],
        ];
    }
}
