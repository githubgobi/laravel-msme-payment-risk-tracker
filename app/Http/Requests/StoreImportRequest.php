<?php

namespace App\Http\Requests;

use App\Enums\ImportSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'file'   => [
                'required',
                'file',
                'max:10240',
                'mimes:csv,txt,xlsx,xls,xml',
            ],
            'source' => [
                'required',
                Rule::in([ImportSource::Csv->value, ImportSource::TallyXml->value]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.max'      => 'The file must not exceed 10MB.',
            'file.mimes'    => 'Only CSV, Excel (.xlsx/.xls), or XML files are accepted.',
            'source.required' => 'Please select the import source type.',
            'source.in'       => 'Invalid import source. Choose CSV/Excel or Tally XML.',
        ];
    }
}
