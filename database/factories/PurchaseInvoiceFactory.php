<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\VendorCategory;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Services\MsmeDeadlineEngine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseInvoiceFactory extends Factory
{
    protected $model = PurchaseInvoice::class;

    public function definition(): array
    {
        $invoiceDate      = Carbon::create(2026, fake()->numberBetween(4, 5), fake()->numberBetween(1, 28));
        $agreementExists  = false;
        $effectiveDeadline = $invoiceDate->copy()->addDays(MsmeDeadlineEngine::NO_AGREEMENT_DAYS);
        $financialYear    = '2026-27';

        return [
            'tenant_id'                => Tenant::factory(),
            'vendor_id'                => Vendor::factory(),
            'invoice_number'           => 'INV-' . fake()->numerify('####-####'),
            'invoice_date'             => $invoiceDate->toDateString(),
            'amount'                   => fake()->numberBetween(50000, 500000),
            'paid_amount'              => 0.00,
            'balance'                  => fake()->numberBetween(50000, 500000),
            'currency'                 => 'INR',
            'agreement_exists'         => $agreementExists,
            'effective_deadline'       => $effectiveDeadline->toDateString(),
            'vendor_category_snapshot' => VendorCategory::Micro->value,
            'financial_year'           => $financialYear,
            'disallowance_amount'      => 0.00,
            'interest_amount'          => 0.00,
            'status'                   => InvoiceStatus::Pending->value,
            'narration'                => null,
            'created_by'               => null,
            'updated_by'               => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(function (array $attributes) {
            $invoiceDate      = Carbon::create(2026, 6, fake()->numberBetween(5, 18));
            $effectiveDeadline = $invoiceDate->copy()->addDays(15);

            return [
                'invoice_date'       => $invoiceDate->toDateString(),
                'effective_deadline' => $effectiveDeadline->toDateString(),
                'paid_amount'        => 0.00,
                'balance'            => $attributes['amount'],
                'status'             => InvoiceStatus::Pending->value,
                'disallowance_amount' => 0.00,
                'interest_amount'    => 0.00,
            ];
        });
    }

    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            $amount   = fake()->numberBetween(100000, 500000);
            $paid     = fake()->numberBetween(10000, (int) ($amount * 0.7));
            $invoiceDate       = Carbon::create(2026, 5, fake()->numberBetween(1, 25));
            $effectiveDeadline = $invoiceDate->copy()->addDays(15);

            return [
                'invoice_date'       => $invoiceDate->toDateString(),
                'effective_deadline' => $effectiveDeadline->toDateString(),
                'amount'             => $amount,
                'paid_amount'        => $paid,
                'balance'            => $amount - $paid,
                'status'             => InvoiceStatus::Partial->value,
                'disallowance_amount' => 0.00,
                'interest_amount'    => 0.00,
            ];
        });
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $amount      = fake()->numberBetween(50000, 300000);
            $invoiceDate = Carbon::create(2026, 4, fake()->numberBetween(1, 20));
            $deadline    = $invoiceDate->copy()->addDays(15);

            return [
                'invoice_date'       => $invoiceDate->toDateString(),
                'effective_deadline' => $deadline->toDateString(),
                'amount'             => $amount,
                'paid_amount'        => $amount,
                'balance'            => 0.00,
                'status'             => InvoiceStatus::Paid->value,
                'disallowance_amount' => 0.00,
                'interest_amount'    => 0.00,
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state(function (array $attributes) {
            $amount      = fake()->numberBetween(100000, 400000);
            $invoiceDate = Carbon::create(2026, 4, fake()->numberBetween(1, 10));
            $deadline    = $invoiceDate->copy()->addDays(15); // Deadline in April — now overdue

            return [
                'invoice_date'       => $invoiceDate->toDateString(),
                'effective_deadline' => $deadline->toDateString(),
                'amount'             => $amount,
                'paid_amount'        => 0.00,
                'balance'            => $amount,
                'status'             => InvoiceStatus::Overdue->value,
                'disallowance_amount' => round($amount * 0.18, 2),
                'interest_amount'    => round($amount * 0.018, 2),
            ];
        });
    }

    public function disallowed(): static
    {
        return $this->state(function (array $attributes) {
            $amount      = fake()->numberBetween(100000, 500000);
            $invoiceDate = Carbon::create(2025, fake()->numberBetween(9, 12), fake()->numberBetween(1, 28));
            $deadline    = $invoiceDate->copy()->addDays(15);

            return [
                'invoice_date'       => $invoiceDate->toDateString(),
                'effective_deadline' => $deadline->toDateString(),
                'financial_year'     => '2025-26',
                'amount'             => $amount,
                'paid_amount'        => 0.00,
                'balance'            => $amount,
                'status'             => InvoiceStatus::Disallowed->value,
                'disallowance_amount' => $amount,
                'interest_amount'    => round($amount * 0.05, 2),
            ];
        });
    }

    public function withAgreement(): static
    {
        return $this->state(function (array $attributes) {
            $invoiceDate = Carbon::parse($attributes['invoice_date'] ?? now()->subDays(30));
            $deadline    = $invoiceDate->copy()->addDays(MsmeDeadlineEngine::AGREEMENT_DAYS);

            return [
                'agreement_exists'   => true,
                'effective_deadline' => $deadline->toDateString(),
            ];
        });
    }

    public function micro(): static
    {
        return $this->state(['vendor_category_snapshot' => VendorCategory::Micro->value]);
    }

    public function small(): static
    {
        return $this->state(['vendor_category_snapshot' => VendorCategory::Small->value]);
    }

    public function medium(): static
    {
        return $this->state([
            'vendor_category_snapshot' => VendorCategory::Medium->value,
            'disallowance_amount'      => 0.00,
            'interest_amount'          => 0.00,
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }

    public function forVendor(Vendor $vendor): static
    {
        return $this->state([
            'tenant_id' => $vendor->tenant_id,
            'vendor_id' => $vendor->id,
            'vendor_category_snapshot' => $vendor->category?->value ?? VendorCategory::Micro->value,
        ]);
    }
}
