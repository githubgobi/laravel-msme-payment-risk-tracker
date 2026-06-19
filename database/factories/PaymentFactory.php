<?php

namespace Database\Factories;

use App\Enums\PaymentMode;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'tenant_id'        => Tenant::factory(),
            'invoice_id'       => PurchaseInvoice::factory(),
            'payment_date'     => fake()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'amount'           => fake()->numberBetween(10000, 100000),
            'payment_mode'     => fake()->randomElement([
                PaymentMode::Neft->value,
                PaymentMode::Rtgs->value,
                PaymentMode::Upi->value,
            ]),
            'reference_number' => fake()->optional(0.7)->bothify('TXN-####??####'),
            'notes'            => null,
            'created_by'       => null,
            'updated_by'       => null,
        ];
    }

    public function neft(): static
    {
        return $this->state(['payment_mode' => PaymentMode::Neft->value]);
    }

    public function upi(): static
    {
        return $this->state(['payment_mode' => PaymentMode::Upi->value]);
    }

    public function forInvoice(PurchaseInvoice $invoice): static
    {
        return $this->state([
            'tenant_id'  => $invoice->tenant_id,
            'invoice_id' => $invoice->id,
        ]);
    }
}
