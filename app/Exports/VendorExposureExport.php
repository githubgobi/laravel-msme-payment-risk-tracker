<?php

namespace App\Exports;

use App\Models\Tenant;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class VendorExposureExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithTitle,
    ShouldAutoSize
{
    private array $report;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly int $year,
        private readonly ReportService $reportService,
    ) {
        $this->report = $reportService->annualSummary($tenant, $year);
    }

    public function title(): string
    {
        return "FY {$this->year}-" . ($this->year + 1);
    }

    public function collection(): Collection
    {
        return collect($this->report['vendor_rows']);
    }

    public function headings(): array
    {
        return [
            'Vendor Name',
            'GSTIN',
            'Category',
            'Invoices',
            'Total Amount (₹)',
            'Paid Amount (₹)',
            'Outstanding (₹)',
            'Overdue Invoices',
            'Disallowance Amt (₹)',
            'Interest Payable (₹)',
        ];
    }

    public function map($row): array
    {
        return [
            $row['vendor_name'],
            $row['vendor_gstin'] ?? '—',
            $row['category'],
            $row['invoice_count'],
            number_format($row['total_amount'], 2),
            number_format($row['paid_amount'], 2),
            number_format($row['outstanding_amount'], 2),
            $row['overdue_invoices'],
            number_format($row['disallowance_amount'], 2),
            number_format($row['interest_payable'], 2),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'size' => 11],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E40AF']],
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            ],
        ];
    }
}
