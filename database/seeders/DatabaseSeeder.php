<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMode;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\VendorCategory;
use App\Models\ImportBatch;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedArjunTextiles();
        $this->seedRajeshAssociates();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tenant 1 — Arjun Textiles Pvt Ltd (Starter plan, Active)
    // ─────────────────────────────────────────────────────────────────────────
    private function seedArjunTextiles(): void
    {
        $tenant = Tenant::create([
            'name'                  => 'Arjun Textiles Pvt Ltd',
            'email'                 => 'accounts@arjuntextiles.com',
            'phone'                 => '+911122334455',
            'gstin'                 => '29AADCA8719Q1Z3',
            'pan'                   => 'AADCA8719Q',
            'state'                 => 'Karnataka',
            'city'                  => 'Bengaluru',
            'address'               => '14, Industrial Estate, Peenya, Bengaluru 560058',
            'plan'                  => TenantPlan::Starter->value,
            'subscription_status'   => TenantStatus::Active->value,
            'subscription_ends_at'  => now()->addYear(),
            'rbi_bank_rate'         => 6.75,
            'is_active'             => true,
            'settings'              => [
                'alerts' => [
                    'email_enabled'   => true,
                    'email_recipients' => ['accounts@arjuntextiles.com'],
                    'whatsapp_enabled' => false,
                    't10_enabled'      => true,
                    't3_enabled'       => true,
                    'overdue_enabled'  => true,
                ],
            ],
        ]);

        // Users
        $owner = User::create([
            'tenant_id'   => $tenant->id,
            'name'        => 'Arjun Sharma',
            'email'       => 'arjun@arjuntextiles.com',
            'password'    => Hash::make('password'),
            'role'        => UserRole::Owner->value,
            'phone'       => '+919876543210',
            'is_active'   => true,
        ]);

        User::create([
            'tenant_id'   => $tenant->id,
            'name'        => 'Priya Mehta',
            'email'       => 'priya@arjuntextiles.com',
            'password'    => Hash::make('password'),
            'role'        => UserRole::Finance->value,
            'phone'       => '+919876543211',
            'is_active'   => true,
        ]);

        // Vendors
        $vendorData = [
            ['name' => 'Suresh Yarns Pvt Ltd',         'category' => VendorCategory::Micro,          'gstin' => '29ABCDE1234F1Z5', 'udyam' => 'UDYAM-KA-01-0001234'],
            ['name' => 'Global Thread Works',           'category' => VendorCategory::Micro,          'gstin' => null,              'udyam' => 'UDYAM-TN-05-0007890'],
            ['name' => 'Kamath Fabrics',                'category' => VendorCategory::Micro,          'gstin' => '33FGHIJ5678K2Y6', 'udyam' => null],
            ['name' => 'Maharashtra Dye Chemicals',     'category' => VendorCategory::Small,          'gstin' => '27KLMNO9012L3A7', 'udyam' => 'UDYAM-MH-12-0003456'],
            ['name' => 'Punjab Spinning Mills Ltd',     'category' => VendorCategory::Small,          'gstin' => '03PQRST3456M4B8', 'udyam' => null],
            ['name' => 'Reliance Industries Ltd',       'category' => VendorCategory::Large,          'gstin' => '27AAACR0541Q1ZI', 'udyam' => null],
            ['name' => 'Bharat Packaging Ltd',          'category' => VendorCategory::Medium,         'gstin' => null,              'udyam' => null],
            ['name' => 'Sunrise Accessories',           'category' => VendorCategory::Unclassified,   'gstin' => null,              'udyam' => null],
        ];

        $vendors = [];
        foreach ($vendorData as $vd) {
            $vendors[] = Vendor::create([
                'tenant_id'    => $tenant->id,
                'name'         => $vd['name'],
                'category'     => $vd['category']->value,
                'gstin'        => $vd['gstin'],
                'udyam_number' => $vd['udyam'],
                'is_active'    => true,
                'created_by'   => $owner->id,
            ]);
        }

        [$micro1, $micro2, $micro3, $small1, $small2, $large1, $medium1, $unclassified1] = $vendors;

        // Import batch
        $batch = ImportBatch::create([
            'tenant_id'         => $tenant->id,
            'source'            => 'csv',
            'original_filename' => 'arjun-invoices-2026.csv',
            'total_rows'        => 22,
            'processed_rows'    => 20,
            'skipped_rows'      => 1,
            'failed_rows'       => 1,
            'status'            => 'completed',
            'started_at'        => now()->subDays(2),
            'completed_at'      => now()->subDays(2)->addMinutes(3),
            'created_by'        => $owner->id,
        ]);

        // ── Invoices ─────────────────────────────────────────────────────────

        $now = Carbon::now();

        // Pending invoices (deadline in future — safe zone)
        $this->invoice($tenant, $micro1, $batch, [
            'invoice_number'   => 'INV-2026-0101',
            'invoice_date'     => '2026-06-15',
            'amount'           => 150000,
            'effective_deadline' => '2026-06-30',
            'status'           => InvoiceStatus::Pending->value,
        ]);

        $this->invoice($tenant, $micro2, $batch, [
            'invoice_number'   => 'INV-2026-0102',
            'invoice_date'     => '2026-06-10',
            'amount'           => 280000,
            'effective_deadline' => '2026-06-25',
            'status'           => InvoiceStatus::Pending->value,
        ]);

        $this->invoice($tenant, $small1, $batch, [
            'invoice_number'   => 'INV-2026-0103',
            'invoice_date'     => '2026-06-12',
            'amount'           => 475000,
            'effective_deadline' => '2026-06-27',
            'status'           => InvoiceStatus::Pending->value,
            'vendor_category_snapshot' => VendorCategory::Small->value,
        ]);

        $this->invoice($tenant, $micro3, $batch, [
            'invoice_number'   => 'INV-2026-0104',
            'invoice_date'     => '2026-06-18',
            'amount'           => 95000,
            'effective_deadline' => '2026-07-03',
            'status'           => InvoiceStatus::Pending->value,
        ]);

        // Pending with agreement (45-day deadline)
        $this->invoice($tenant, $small2, $batch, [
            'invoice_number'    => 'INV-2026-0105',
            'invoice_date'      => '2026-05-20',
            'amount'            => 350000,
            'agreement_exists'  => true,
            'effective_deadline' => '2026-07-04',
            'status'            => InvoiceStatus::Pending->value,
            'vendor_category_snapshot' => VendorCategory::Small->value,
        ]);

        // T10 warning zone (deadline in 8-10 days from 2026-06-19)
        $this->invoice($tenant, $micro1, $batch, [
            'invoice_number'   => 'INV-2026-0106',
            'invoice_date'     => '2026-06-13',
            'amount'           => 220000,
            'effective_deadline' => '2026-06-28', // 9 days away
            'status'           => InvoiceStatus::Pending->value,
        ]);

        // T3 urgent zone (deadline in 1-3 days)
        $this->invoice($tenant, $micro2, $batch, [
            'invoice_number'   => 'INV-2026-0107',
            'invoice_date'     => '2026-06-06',
            'amount'           => 180000,
            'effective_deadline' => '2026-06-21', // 2 days away
            'status'           => InvoiceStatus::Pending->value,
        ]);

        // Partial payments
        $partialInvoice1 = $this->invoice($tenant, $micro1, $batch, [
            'invoice_number'   => 'INV-2026-0108',
            'invoice_date'     => '2026-05-20',
            'amount'           => 320000,
            'paid_amount'      => 100000,
            'balance'          => 220000,
            'effective_deadline' => '2026-06-04',
            'status'           => InvoiceStatus::Partial->value,
        ]);

        $this->payment($tenant, $partialInvoice1, [
            'payment_date'     => '2026-05-28',
            'amount'           => 100000,
            'payment_mode'     => PaymentMode::Neft->value,
            'reference_number' => 'TXN-NEFT-2405201',
        ], $owner->id);

        $partialInvoice2 = $this->invoice($tenant, $small1, $batch, [
            'invoice_number'   => 'INV-2026-0109',
            'invoice_date'     => '2026-05-15',
            'amount'           => 580000,
            'paid_amount'      => 200000,
            'balance'          => 380000,
            'effective_deadline' => '2026-05-30',
            'status'           => InvoiceStatus::Partial->value,
            'vendor_category_snapshot' => VendorCategory::Small->value,
        ]);

        $this->payment($tenant, $partialInvoice2, [
            'payment_date'     => '2026-05-22',
            'amount'           => 200000,
            'payment_mode'     => PaymentMode::Rtgs->value,
            'reference_number' => 'TXN-RTGS-2405202',
        ], $owner->id);

        // Fully paid invoices
        $paidInvoice1 = $this->invoice($tenant, $micro2, $batch, [
            'invoice_number'   => 'INV-2026-0201',
            'invoice_date'     => '2026-04-05',
            'amount'           => 240000,
            'paid_amount'      => 240000,
            'balance'          => 0,
            'effective_deadline' => '2026-04-20',
            'status'           => InvoiceStatus::Paid->value,
        ]);

        $this->payment($tenant, $paidInvoice1, [
            'payment_date'     => '2026-04-18',
            'amount'           => 240000,
            'payment_mode'     => PaymentMode::Neft->value,
            'reference_number' => 'TXN-NEFT-2404001',
        ], $owner->id);

        $paidInvoice2 = $this->invoice($tenant, $micro1, $batch, [
            'invoice_number'   => 'INV-2026-0202',
            'invoice_date'     => '2026-04-10',
            'amount'           => 175000,
            'paid_amount'      => 175000,
            'balance'          => 0,
            'effective_deadline' => '2026-04-25',
            'status'           => InvoiceStatus::Paid->value,
        ]);

        $this->payment($tenant, $paidInvoice2, [
            'payment_date'     => '2026-04-22',
            'amount'           => 175000,
            'payment_mode'     => PaymentMode::Upi->value,
            'reference_number' => 'UPI-2404002',
        ], $owner->id);

        $paidInvoice3 = $this->invoice($tenant, $small2, $batch, [
            'invoice_number'   => 'INV-2026-0203',
            'invoice_date'     => '2026-04-02',
            'amount'           => 490000,
            'paid_amount'      => 490000,
            'balance'          => 0,
            'effective_deadline' => '2026-05-17', // 45-day agreement
            'agreement_exists'  => true,
            'status'           => InvoiceStatus::Paid->value,
            'vendor_category_snapshot' => VendorCategory::Small->value,
        ]);

        $this->payment($tenant, $paidInvoice3, [
            'payment_date'     => '2026-05-10',
            'amount'           => 490000,
            'payment_mode'     => PaymentMode::Rtgs->value,
            'reference_number' => 'TXN-RTGS-2405003',
        ], $owner->id);

        // Overdue invoices (deadline passed, still unpaid)
        $this->invoice($tenant, $micro1, $batch, [
            'invoice_number'   => 'INV-2026-0301',
            'invoice_date'     => '2026-04-01',
            'amount'           => 415000,
            'effective_deadline' => '2026-04-16',
            'status'           => InvoiceStatus::Overdue->value,
            'disallowance_amount' => 415000,
            'interest_amount'  => 18675,
        ]);

        $this->invoice($tenant, $micro2, $batch, [
            'invoice_number'   => 'INV-2026-0302',
            'invoice_date'     => '2026-04-05',
            'amount'           => 290000,
            'effective_deadline' => '2026-04-20',
            'status'           => InvoiceStatus::Overdue->value,
            'disallowance_amount' => 290000,
            'interest_amount'  => 13050,
        ]);

        $this->invoice($tenant, $small1, $batch, [
            'invoice_number'   => 'INV-2026-0303',
            'invoice_date'     => '2026-05-01',
            'amount'           => 650000,
            'effective_deadline' => '2026-05-16',
            'status'           => InvoiceStatus::Overdue->value,
            'disallowance_amount' => 650000,
            'interest_amount'  => 29250,
            'vendor_category_snapshot' => VendorCategory::Small->value,
        ]);

        // Large vendor — not subject to 43Bh — no disallowance
        $this->invoice($tenant, $large1, $batch, [
            'invoice_number'   => 'INV-2026-0401',
            'invoice_date'     => '2026-04-10',
            'amount'           => 1200000,
            'effective_deadline' => '2026-04-25',
            'status'           => InvoiceStatus::Pending->value,
            'vendor_category_snapshot' => VendorCategory::Large->value,
            'disallowance_amount' => 0,
            'interest_amount'  => 0,
        ]);

        // Medium vendor — also not subject to 43Bh
        $this->invoice($tenant, $medium1, $batch, [
            'invoice_number'   => 'INV-2026-0402',
            'invoice_date'     => '2026-05-05',
            'amount'           => 750000,
            'effective_deadline' => '2026-05-20',
            'status'           => InvoiceStatus::Pending->value,
            'vendor_category_snapshot' => VendorCategory::Medium->value,
            'disallowance_amount' => 0,
            'interest_amount'  => 0,
        ]);

        // Disallowed from FY 2025-26
        $this->invoice($tenant, $micro3, $batch, [
            'invoice_number'     => 'INV-2025-0901',
            'invoice_date'       => '2025-12-15',
            'amount'             => 380000,
            'effective_deadline' => '2025-12-30',
            'financial_year'     => '2025-26',
            'status'             => InvoiceStatus::Disallowed->value,
            'disallowance_amount' => 380000,
            'interest_amount'    => 57000,
        ]);

        $this->invoice($tenant, $small1, $batch, [
            'invoice_number'     => 'INV-2025-0902',
            'invoice_date'       => '2026-02-20',
            'amount'             => 520000,
            'effective_deadline' => '2026-03-07',
            'financial_year'     => '2025-26',
            'status'             => InvoiceStatus::Disallowed->value,
            'disallowance_amount' => 520000,
            'interest_amount'    => 23400,
            'vendor_category_snapshot' => VendorCategory::Small->value,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tenant 2 — Rajesh & Associates (Growth plan, Trial — 10 days left)
    // ─────────────────────────────────────────────────────────────────────────
    private function seedRajeshAssociates(): void
    {
        $tenant = Tenant::create([
            'name'                 => 'Rajesh & Associates',
            'email'                => 'rajesh@rajeshca.com',
            'phone'                => '+917788990011',
            'gstin'                => null,
            'state'                => 'Maharashtra',
            'city'                 => 'Pune',
            'plan'                 => TenantPlan::Growth->value,
            'subscription_status'  => TenantStatus::Trial->value,
            'trial_ends_at'        => now()->addDays(10),
            'rbi_bank_rate'        => 6.75,
            'is_active'            => true,
            'settings'             => [
                'alerts' => [
                    'email_enabled'    => true,
                    'email_recipients' => ['rajesh@rajeshca.com'],
                    'whatsapp_enabled' => false,
                    't10_enabled'      => true,
                    't3_enabled'       => true,
                    'overdue_enabled'  => true,
                ],
            ],
        ]);

        $owner = User::create([
            'tenant_id'   => $tenant->id,
            'name'        => 'Rajesh Kumar',
            'email'       => 'rajesh@rajeshca.com',
            'password'    => Hash::make('password'),
            'role'        => UserRole::Owner->value,
            'phone'       => '+919898989898',
            'is_active'   => true,
        ]);

        // Vendors
        $vendorData = [
            ['name' => 'Pune Steel Works',         'category' => VendorCategory::Micro],
            ['name' => 'Nashik Agro Products',     'category' => VendorCategory::Micro],
            ['name' => 'Solapur Textiles',         'category' => VendorCategory::Small],
            ['name' => 'Nagpur Auto Parts',        'category' => VendorCategory::Small],
            ['name' => 'Unregistered Supplier A',  'category' => VendorCategory::Unclassified],
            ['name' => 'Unregistered Supplier B',  'category' => VendorCategory::Unclassified],
        ];

        $vendors = [];
        foreach ($vendorData as $vd) {
            $vendors[] = Vendor::create([
                'tenant_id'  => $tenant->id,
                'name'       => $vd['name'],
                'category'   => $vd['category']->value,
                'is_active'  => true,
                'created_by' => $owner->id,
            ]);
        }

        [$micro1, $micro2, $small1, $small2, $unc1, $unc2] = $vendors;

        $batch = ImportBatch::create([
            'tenant_id'         => $tenant->id,
            'source'            => 'csv',
            'original_filename' => 'rajesh-invoices-q1.csv',
            'total_rows'        => 15,
            'processed_rows'    => 15,
            'skipped_rows'      => 0,
            'failed_rows'       => 0,
            'status'            => 'completed',
            'started_at'        => now()->subDay(),
            'completed_at'      => now()->subDay()->addMinutes(2),
            'created_by'        => $owner->id,
        ]);

        // Pending
        $this->invoice($tenant, $micro1, $batch, [
            'invoice_number'   => 'PU-2026-001',
            'invoice_date'     => '2026-06-14',
            'amount'           => 125000,
            'effective_deadline' => '2026-06-29',
            'status'           => InvoiceStatus::Pending->value,
        ]);

        $this->invoice($tenant, $small1, $batch, [
            'invoice_number'   => 'PU-2026-002',
            'invoice_date'     => '2026-06-10',
            'amount'           => 340000,
            'effective_deadline' => '2026-06-25',
            'status'           => InvoiceStatus::Pending->value,
            'vendor_category_snapshot' => VendorCategory::Small->value,
        ]);

        $this->invoice($tenant, $micro2, $batch, [
            'invoice_number'   => 'PU-2026-003',
            'invoice_date'     => '2026-06-17',
            'amount'           => 78000,
            'effective_deadline' => '2026-07-02',
            'status'           => InvoiceStatus::Pending->value,
        ]);

        // Partial
        $partial = $this->invoice($tenant, $micro1, $batch, [
            'invoice_number'   => 'PU-2026-004',
            'invoice_date'     => '2026-05-25',
            'amount'           => 210000,
            'paid_amount'      => 70000,
            'balance'          => 140000,
            'effective_deadline' => '2026-06-09',
            'status'           => InvoiceStatus::Partial->value,
        ]);

        $this->payment($tenant, $partial, [
            'payment_date'     => '2026-06-02',
            'amount'           => 70000,
            'payment_mode'     => PaymentMode::Upi->value,
            'reference_number' => 'UPI-PUNE-001',
        ], $owner->id);

        // Paid
        $paid = $this->invoice($tenant, $small2, $batch, [
            'invoice_number'   => 'PU-2026-005',
            'invoice_date'     => '2026-04-08',
            'amount'           => 295000,
            'paid_amount'      => 295000,
            'balance'          => 0,
            'effective_deadline' => '2026-04-23',
            'status'           => InvoiceStatus::Paid->value,
            'vendor_category_snapshot' => VendorCategory::Small->value,
        ]);

        $this->payment($tenant, $paid, [
            'payment_date'     => '2026-04-20',
            'amount'           => 295000,
            'payment_mode'     => PaymentMode::Neft->value,
            'reference_number' => 'NEFT-PUNE-001',
        ], $owner->id);

        // Overdue
        $this->invoice($tenant, $micro1, $batch, [
            'invoice_number'   => 'PU-2026-006',
            'invoice_date'     => '2026-04-03',
            'amount'           => 155000,
            'effective_deadline' => '2026-04-18',
            'status'           => InvoiceStatus::Overdue->value,
            'disallowance_amount' => 155000,
            'interest_amount'  => 6975,
        ]);

        $this->invoice($tenant, $small1, $batch, [
            'invoice_number'   => 'PU-2026-007',
            'invoice_date'     => '2026-04-12',
            'amount'           => 430000,
            'effective_deadline' => '2026-04-27',
            'status'           => InvoiceStatus::Overdue->value,
            'disallowance_amount' => 430000,
            'interest_amount'  => 19350,
            'vendor_category_snapshot' => VendorCategory::Small->value,
        ]);

        // Disallowed from FY 2025-26
        $this->invoice($tenant, $micro2, $batch, [
            'invoice_number'     => 'PU-2025-099',
            'invoice_date'       => '2026-01-10',
            'amount'             => 195000,
            'effective_deadline' => '2026-01-25',
            'financial_year'     => '2025-26',
            'status'             => InvoiceStatus::Disallowed->value,
            'disallowance_amount' => 195000,
            'interest_amount'    => 29250,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────
    private function invoice(Tenant $tenant, Vendor $vendor, ImportBatch $batch, array $data): PurchaseInvoice
    {
        $amount = $data['amount'] ?? 100000;

        return PurchaseInvoice::create(array_merge([
            'tenant_id'                => $tenant->id,
            'vendor_id'                => $vendor->id,
            'import_batch_id'          => $batch->id,
            'currency'                 => 'INR',
            'agreement_exists'         => false,
            'paid_amount'              => 0.00,
            'balance'                  => $amount,
            'vendor_category_snapshot' => VendorCategory::Micro->value,
            'financial_year'           => '2026-27',
            'disallowance_amount'      => 0.00,
            'interest_amount'          => 0.00,
            'last_computed_at'         => now(),
        ], $data));
    }

    private function payment(Tenant $tenant, PurchaseInvoice $invoice, array $data, int $createdBy): Payment
    {
        return Payment::create(array_merge([
            'tenant_id'  => $tenant->id,
            'invoice_id' => $invoice->id,
            'created_by' => $createdBy,
        ], $data));
    }
}
