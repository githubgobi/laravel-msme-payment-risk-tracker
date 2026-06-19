<?php

namespace App\Http\Requests;

use App\Enums\VendorCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageInvoices() ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name'         => ['required', 'string', 'max:255'],
            'category'     => ['required', Rule::enum(VendorCategory::class)],
            'gstin'        => [
                'nullable',
                'string',
                'size:15',
                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/',
                // GSTIN must be unique per tenant (vendors are tenant-scoped)
                Rule::unique('vendors', 'gstin')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'pan'          => ['nullable', 'string', 'size:10', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'udyam_number' => [
                'nullable',
                'string',
                'regex:/^UDYAM-[A-Z]{2}-\d{2}-\d{7}$/',
                Rule::unique('vendors', 'udyam_number')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'contact_name'  => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'address'       => ['nullable', 'string', 'max:500'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'gstin.size'         => 'GSTIN must be exactly 15 characters.',
            'gstin.regex'        => 'GSTIN format is invalid (e.g. 27AABCU9603R1ZX).',
            'pan.size'           => 'PAN must be exactly 10 characters.',
            'pan.regex'          => 'PAN format is invalid (e.g. ABCDE1234F).',
            'udyam_number.regex' => 'Udyam number format is invalid (e.g. UDYAM-MH-01-0001234).',
        ];
    }
}
