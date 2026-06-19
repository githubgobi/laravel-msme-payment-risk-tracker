<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\ColumnMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColumnMapperTest extends TestCase
{
    private ColumnMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new ColumnMapper();
    }

    #[Test]
    public function resolve_canonical_exact_match(): void
    {
        $this->assertSame('invoice_number', $this->mapper->resolveCanonical('invoice_number'));
        $this->assertSame('vendor_name', $this->mapper->resolveCanonical('vendor_name'));
        $this->assertSame('amount', $this->mapper->resolveCanonical('amount'));
    }

    #[Test]
    public function resolve_canonical_alias_match(): void
    {
        $this->assertSame('invoice_number', $this->mapper->resolveCanonical('bill_number'));
        $this->assertSame('invoice_number', $this->mapper->resolveCanonical('voucher_number'));
        $this->assertSame('vendor_name', $this->mapper->resolveCanonical('party_name'));
        $this->assertSame('vendor_name', $this->mapper->resolveCanonical('supplier_name'));
        $this->assertSame('amount', $this->mapper->resolveCanonical('invoice_amount'));
        $this->assertSame('amount', $this->mapper->resolveCanonical('gross_amount'));
    }

    #[Test]
    public function resolve_canonical_is_case_insensitive(): void
    {
        $this->assertSame('vendor_name', $this->mapper->resolveCanonical('Vendor_Name'));
        $this->assertSame('amount', $this->mapper->resolveCanonical('AMOUNT'));
    }

    #[Test]
    public function resolve_canonical_normalizes_special_chars(): void
    {
        // "invoice number" with space should map to "invoice_number"
        $this->assertSame('invoice_number', $this->mapper->resolveCanonical('invoice_no'));
        $this->assertSame('invoice_date', $this->mapper->resolveCanonical('invoice_date'));
    }

    #[Test]
    public function resolve_canonical_returns_null_for_unknown_header(): void
    {
        $this->assertNull($this->mapper->resolveCanonical('unknown_column'));
        $this->assertNull($this->mapper->resolveCanonical('foobar'));
        $this->assertNull($this->mapper->resolveCanonical(''));
    }

    #[Test]
    public function map_row_maps_known_columns(): void
    {
        $raw = [
            'invoice_number' => 'INV-001',
            'vendor_name'    => 'ABC Ltd',
            'invoice_date'   => '2025-01-15',
            'amount'         => '500000',
        ];

        $mapped = $this->mapper->mapRow($raw);

        $this->assertSame('INV-001', $mapped['invoice_number']);
        $this->assertSame('ABC Ltd', $mapped['vendor_name']);
    }

    #[Test]
    public function map_row_drops_unrecognized_columns(): void
    {
        $raw    = ['unknown_col' => 'value', 'invoice_number' => 'INV-001'];
        $mapped = $this->mapper->mapRow($raw);

        $this->assertArrayNotHasKey('unknown_col', $mapped);
        $this->assertSame('INV-001', $mapped['invoice_number']);
    }

    #[Test]
    public function map_row_handles_alias_keys(): void
    {
        $raw = [
            'bill_number'    => 'INV-002',
            'party_name'     => 'XYZ Co',
            'invoice_amount' => '250000',
            'bill_date'      => '2025-02-01',
        ];

        $mapped = $this->mapper->mapRow($raw);

        $this->assertSame('INV-002', $mapped['invoice_number']);
        $this->assertSame('XYZ Co', $mapped['vendor_name']);
        $this->assertSame('250000', $mapped['amount']);
        $this->assertSame('2025-02-01', $mapped['invoice_date']);
    }

    #[Test]
    public function map_row_empty_input_returns_empty_array(): void
    {
        $this->assertSame([], $this->mapper->mapRow([]));
    }

    #[Test]
    public function canonical_fields_contains_all_required_fields(): void
    {
        $fields = $this->mapper->canonicalFields();

        $this->assertContains('invoice_number', $fields);
        $this->assertContains('invoice_date', $fields);
        $this->assertContains('vendor_name', $fields);
        $this->assertContains('amount', $fields);
        $this->assertContains('gstin', $fields);
        $this->assertContains('udyam_number', $fields);
        $this->assertContains('paid_amount', $fields);
        $this->assertContains('agreement_exists', $fields);
        $this->assertContains('narration', $fields);
    }
}
