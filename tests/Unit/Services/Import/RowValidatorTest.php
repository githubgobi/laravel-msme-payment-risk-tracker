<?php

namespace Tests\Unit\Services\Import;

use App\DTOs\ImportRow;
use App\Services\Import\RowValidator;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RowValidatorTest extends TestCase
{
    private RowValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new RowValidator();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validate() — required fields
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function valid_row_returns_no_errors(): void
    {
        $row    = $this->makeRow();
        $errors = $this->validator->validate($row);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function missing_invoice_number_returns_error(): void
    {
        $errors = $this->validator->validate($this->makeRow(invoiceNumber: ''));
        $this->assertContainsStringLike('invoice_number is required', $errors);
    }

    #[Test]
    public function invoice_number_exceeding_100_chars_returns_error(): void
    {
        $errors = $this->validator->validate($this->makeRow(invoiceNumber: str_repeat('X', 101)));
        $this->assertContainsStringLike('must not exceed 100', $errors);
    }

    #[Test]
    public function missing_invoice_date_returns_error(): void
    {
        $errors = $this->validator->validate($this->makeRow(invoiceDate: ''));
        $this->assertContainsStringLike('invoice_date is required', $errors);
    }

    #[Test]
    public function unparseable_invoice_date_returns_error(): void
    {
        $errors = $this->validator->validate($this->makeRow(invoiceDate: 'not-a-date'));
        $this->assertContainsStringLike('invoice_date', $errors);
    }

    #[Test]
    public function missing_vendor_name_returns_error(): void
    {
        $errors = $this->validator->validate($this->makeRow(vendorName: ''));
        $this->assertContainsStringLike('vendor_name is required', $errors);
    }

    #[Test]
    public function vendor_name_too_short_returns_error(): void
    {
        $errors = $this->validator->validate($this->makeRow(vendorName: 'A'));
        $this->assertContainsStringLike('at least 2 characters', $errors);
    }

    #[Test]
    public function missing_amount_returns_error(): void
    {
        $errors = $this->validator->validate($this->makeRow(amount: ''));
        $this->assertContainsStringLike('amount is required', $errors);
    }

    #[Test]
    public function non_numeric_amount_returns_error(): void
    {
        $errors = $this->validator->validate($this->makeRow(amount: 'five lakhs'));
        $this->assertContainsStringLike('must be a number', $errors);
    }

    #[Test]
    public function negative_amount_is_accepted(): void
    {
        // Credit notes have negative amounts
        $errors = $this->validator->validate($this->makeRow(amount: '-50000'));
        $this->assertEmpty($errors);
    }

    #[Test]
    public function zero_amount_is_accepted(): void
    {
        $errors = $this->validator->validate($this->makeRow(amount: '0'));
        $this->assertEmpty($errors);
    }

    #[Test]
    public function non_numeric_paid_amount_returns_error(): void
    {
        $errors = $this->validator->validate($this->makeRow(paidAmount: 'abc'));
        $this->assertContainsStringLike('paid_amount', $errors);
    }

    #[Test]
    public function empty_paid_amount_is_accepted(): void
    {
        $errors = $this->validator->validate($this->makeRow(paidAmount: ''));
        $this->assertEmpty($errors);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GSTIN validation
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function valid_gstin_is_accepted(): void
    {
        $errors = $this->validator->validate($this->makeRow(gstin: '22AAAAA0000A1Z5'));
        $this->assertEmpty($errors);
    }

    #[Test]
    public function invalid_gstin_format_returns_error(): void
    {
        $errors = $this->validator->validate($this->makeRow(gstin: 'INVALID'));
        $this->assertContainsStringLike('valid GSTIN', $errors);
    }

    #[Test]
    public function gstin_wrong_length_returns_error(): void
    {
        $errors = $this->validator->validate($this->makeRow(gstin: '22AAAAA0000A1Z'));
        $this->assertContainsStringLike('valid GSTIN', $errors);
    }

    #[Test]
    public function empty_gstin_is_accepted(): void
    {
        $errors = $this->validator->validate($this->makeRow(gstin: ''));
        $this->assertEmpty($errors);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Udyam number validation
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function valid_udyam_number_is_accepted(): void
    {
        $errors = $this->validator->validate($this->makeRow(udyamNumber: 'UDYAM-TN-01-0000001'));
        $this->assertEmpty($errors);
    }

    #[Test]
    public function invalid_udyam_format_returns_error(): void
    {
        $errors = $this->validator->validate($this->makeRow(udyamNumber: 'UDYAM-123'));
        $this->assertContainsStringLike('valid format', $errors);
    }

    #[Test]
    public function empty_udyam_number_is_accepted(): void
    {
        $errors = $this->validator->validate($this->makeRow(udyamNumber: ''));
        $this->assertEmpty($errors);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // parseDate()
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('dateFormatProvider')]
    public function parse_date_handles_multiple_formats(string $input, string $expectedDate): void
    {
        $result = $this->validator->parseDate($input);

        $this->assertNotNull($result, "parseDate should parse '{$input}'");
        $this->assertSame($expectedDate, $result->toDateString());
    }

    public static function dateFormatProvider(): array
    {
        return [
            'ISO 8601'          => ['2025-01-15', '2025-01-15'],
            'Indian dash'       => ['15-01-2025', '2025-01-15'],
            'Indian slash'      => ['15/01/2025', '2025-01-15'],
            'Tally YYYYMMDD'    => ['20250115',   '2025-01-15'],
            'Human readable'    => ['15 Jan 2025', '2025-01-15'],
            'Dash month abbrev' => ['15-Jan-2025', '2025-01-15'],
        ];
    }

    #[Test]
    public function parse_date_returns_null_for_garbage_input(): void
    {
        $this->assertNull($this->validator->parseDate('not-a-date'));
        $this->assertNull($this->validator->parseDate('32-13-2025'));
    }

    #[Test]
    public function parse_date_returns_start_of_day(): void
    {
        $result = $this->validator->parseDate('2025-01-15');
        $this->assertSame('00:00:00', $result->format('H:i:s'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // parseAmount()
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function parse_amount_handles_indian_comma_format(): void
    {
        $this->assertSame(500000.0, $this->validator->parseAmount('5,00,000'));
    }

    #[Test]
    public function parse_amount_handles_plain_number(): void
    {
        $this->assertSame(500000.0, $this->validator->parseAmount('500000'));
    }

    #[Test]
    public function parse_amount_handles_decimal(): void
    {
        $this->assertSame(500000.50, $this->validator->parseAmount('5,00,000.50'));
    }

    #[Test]
    public function parse_amount_returns_null_for_non_numeric(): void
    {
        $this->assertNull($this->validator->parseAmount('five lakhs'));
    }

    #[Test]
    public function parse_amount_handles_negative(): void
    {
        $this->assertSame(-50000.0, $this->validator->parseAmount('-50,000'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // parseAgreementExists()
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('agreementTruthyProvider')]
    public function parse_agreement_exists_truthy_values(string $input): void
    {
        $this->assertTrue($this->validator->parseAgreementExists($input));
    }

    public static function agreementTruthyProvider(): array
    {
        return [
            ['true'], ['True'], ['TRUE'], ['1'], ['yes'], ['Yes'], ['YES'], ['y'], ['Y'],
        ];
    }

    #[Test]
    #[DataProvider('agreementFalsyProvider')]
    public function parse_agreement_exists_falsy_values(string $input): void
    {
        $this->assertFalse($this->validator->parseAgreementExists($input));
    }

    public static function agreementFalsyProvider(): array
    {
        return [
            ['false'], ['0'], ['no'], ['n'], [''], ['anything'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Multiple errors on one row
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function row_with_multiple_errors_returns_all_errors(): void
    {
        $row    = $this->makeRow(invoiceNumber: '', invoiceDate: '', vendorName: '');
        $errors = $this->validator->validate($row);

        $this->assertCount(3, $errors);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeRow(
        int    $rowNumber    = 2,
        string $invoiceNumber = 'INV-001',
        string $invoiceDate  = '15-01-2025',
        string $vendorName   = 'ABC Suppliers',
        string $amount       = '100000',
        string $gstin        = '',
        string $udyamNumber  = '',
        string $paidAmount   = '0',
        string $agreementExists = 'false',
        string $narration    = '',
    ): ImportRow {
        return new ImportRow(
            rowNumber:       $rowNumber,
            invoiceNumber:   $invoiceNumber,
            invoiceDate:     $invoiceDate,
            vendorName:      $vendorName,
            amount:          $amount,
            gstin:           $gstin,
            udyamNumber:     $udyamNumber,
            paidAmount:      $paidAmount,
            agreementExists: $agreementExists,
            narration:       $narration,
        );
    }

    private function assertContainsStringLike(string $needle, array $errors): void
    {
        foreach ($errors as $error) {
            if (str_contains($error, $needle)) {
                $this->assertTrue(true);
                return;
            }
        }
        $this->fail("Expected error containing '{$needle}' but got: " . implode(', ', $errors));
    }
}
