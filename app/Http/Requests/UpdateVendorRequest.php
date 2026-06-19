<?php

namespace App\Http\Requests;

use App\Enums\VendorCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorRequest extends FormRequest
{
    private const GSTIN_PATTERN  = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';
    private const UDYAM_PATTERN  = '/^UDYAM-[A-Z]{2}-\d{2}-\d{7}$/';

    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $vendor   = $this->route('vendor');
        $tenantId = auth()->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:200'],

            'category' => ['required', Rule::enum(VendorCategory::class)],

            'gstin' => [
                'nullable',
                'string',
                'size:15',
                'regex:' . self::GSTIN_PATTERN,
                // Unique per tenant — ignore current vendor's own GSTIN
                Rule::unique('vendors', 'gstin')
                    ->where('tenant_id', $tenantId)
                    ->ignore($vendor?->id),
            ],

            'udyam_number' => [
                'nullable',
                'string',
                'regex:' . self::UDYAM_PATTERN,
            ],

            'pan' => [
                'nullable',
                'string',
                'size:10',
                'alpha_num',
            ],

            'phone'          => ['nullable', 'string', 'max:15'],
            'email'          => ['nullable', 'email', 'max:200'],
            'state'          => ['nullable', 'string', 'max:50'],
            'city'           => ['nullable', 'string', 'max:100'],
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'is_active'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'gstin.regex'        => 'GSTIN must be 15 characters in the correct format (e.g. 22AAAAA0000A1Z5).',
            'gstin.unique'       => 'A vendor with this GSTIN already exists for your organisation.',
            'udyam_number.regex' => 'Udyam number must be in the format UDYAM-XX-00-0000000.',
            'pan.size'           => 'PAN must be exactly 10 characters.',
            'pan.alpha_num'      => 'PAN must be alphanumeric.',
        ];
    }
}
