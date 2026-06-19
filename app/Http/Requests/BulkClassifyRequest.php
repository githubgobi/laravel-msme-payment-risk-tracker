<?php

namespace App\Http\Requests;

use App\Enums\VendorCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkClassifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'vendor_ids'   => ['required', 'array', 'min:1', 'max:100'],
            'vendor_ids.*' => ['integer', 'min:1'],
            'category'     => ['required', Rule::enum(VendorCategory::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'vendor_ids.required' => 'Please select at least one vendor.',
            'vendor_ids.max'      => 'You can classify up to 100 vendors at a time.',
            'category.required'   => 'Please select a category.',
        ];
    }
}
