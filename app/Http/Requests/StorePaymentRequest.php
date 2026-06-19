<?php

namespace App\Http\Requests;

use App\Enums\PaymentMode;
use App\Models\PurchaseInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canManageInvoices();
    }

    public function rules(): array
    {
        /** @var PurchaseInvoice $invoice */
        $invoice = $this->route('invoice');
        $balance = (float) $invoice->amount - (float) $invoice->paid_amount;

        return [
            'amount'           => ['required', 'numeric', 'min:0.01', "max:{$balance}"],
            'payment_date'     => ['required', 'date', 'before_or_equal:today'],
            'payment_mode'     => ['required', new Enum(PaymentMode::class)],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.max' => 'Payment amount cannot exceed the remaining balance.',
        ];
    }
}
