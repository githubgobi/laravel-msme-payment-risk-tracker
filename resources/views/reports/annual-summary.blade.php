<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Section 43B(h) Annual Summary – {{ $report['financial_year'] }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; color: #1a1a1a; }
    .page { padding: 24pt 30pt; }

    /* Header */
    .header { border-bottom: 2pt solid #1E3A8A; padding-bottom: 12pt; margin-bottom: 16pt; }
    .header-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .company { font-size: 14pt; font-weight: bold; color: #1E3A8A; }
    .report-title { font-size: 11pt; font-weight: bold; margin-top: 2pt; }
    .meta { font-size: 8pt; color: #555; margin-top: 3pt; }
    .badge { display: inline-block; background: #DBEAFE; color: #1E40AF; padding: 2pt 6pt; border-radius: 3pt; font-size: 8pt; font-weight: bold; }

    /* Summary box */
    .summary-grid { display: table; width: 100%; margin-bottom: 16pt; border-collapse: collapse; }
    .summary-cell { display: table-cell; width: 20%; padding: 8pt 10pt; background: #F8FAFF; border: 0.5pt solid #DBEAFE; text-align: center; }
    .summary-label { font-size: 7.5pt; color: #6B7280; }
    .summary-value { font-size: 12pt; font-weight: bold; color: #1E40AF; margin-top: 2pt; }
    .summary-value.danger { color: #DC2626; }
    .summary-value.warning { color: #D97706; }

    /* Table */
    table { width: 100%; border-collapse: collapse; margin-bottom: 12pt; }
    thead tr { background: #1E3A8A; color: #fff; }
    thead th { padding: 5pt 6pt; text-align: left; font-size: 8pt; font-weight: bold; }
    thead th.right { text-align: right; }
    tbody tr:nth-child(even) { background: #F9FAFB; }
    tbody tr:nth-child(odd)  { background: #ffffff; }
    tbody td { padding: 4pt 6pt; font-size: 8pt; border-bottom: 0.5pt solid #E5E7EB; }
    tbody td.right { text-align: right; }
    tbody td.danger { color: #DC2626; font-weight: bold; }
    tfoot tr { background: #F1F5F9; font-weight: bold; }
    tfoot td { padding: 5pt 6pt; font-size: 8pt; border-top: 1pt solid #1E3A8A; }
    tfoot td.right { text-align: right; }

    /* Legal notice */
    .notice { background: #FFFBEB; border: 0.5pt solid #FDE68A; padding: 8pt 10pt; border-radius: 3pt; margin-top: 8pt; }
    .notice p { font-size: 7.5pt; color: #92400E; line-height: 1.5; }

    /* Footer */
    .footer { margin-top: 16pt; border-top: 0.5pt solid #D1D5DB; padding-top: 6pt; font-size: 7pt; color: #9CA3AF; display: flex; justify-content: space-between; }
</style>
</head>
<body>
<div class="page">

    <!-- ── Header ── -->
    <div class="header">
        <div class="header-top">
            <div>
                <div class="company">{{ $report['tenant_name'] }}</div>
                @if($report['tenant_gstin'])
                <div class="meta">GSTIN: {{ $report['tenant_gstin'] }}</div>
                @endif
            </div>
            <div style="text-align:right">
                <div class="report-title">Section 43B(h) Annual Disallowance Summary</div>
                <div style="margin-top:4pt"><span class="badge">{{ $report['financial_year'] }}</span></div>
                <div class="meta" style="margin-top:4pt">Period: {{ \Carbon\Carbon::parse($report['fy_start'])->format('d M Y') }} – {{ \Carbon\Carbon::parse($report['fy_end'])->format('d M Y') }}</div>
                <div class="meta">RBI Bank Rate: {{ $report['rbi_bank_rate'] }}% &nbsp;|&nbsp; Applicable Rate: <strong>{{ $report['applicable_rate'] }}% p.a.</strong></div>
            </div>
        </div>
    </div>

    <!-- ── Summary tiles ── -->
    <div class="summary-grid">
        <div class="summary-cell">
            <div class="summary-label">Total Invoice Value</div>
            <div class="summary-value">₹{{ number_format($report['total_invoice_amount'], 0) }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Total Paid</div>
            <div class="summary-value">₹{{ number_format($report['total_paid'], 0) }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Outstanding</div>
            <div class="summary-value warning">₹{{ number_format($report['total_outstanding'], 0) }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">43B(h) Disallowance</div>
            <div class="summary-value danger">₹{{ number_format($report['disallowance_amount'], 0) }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Interest Payable</div>
            <div class="summary-value danger">₹{{ number_format($report['total_interest'], 0) }}</div>
        </div>
    </div>

    <!-- ── Vendor detail table ── -->
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Vendor Name</th>
                <th>GSTIN</th>
                <th>Category</th>
                <th class="right">Invoices</th>
                <th class="right">Amount (₹)</th>
                <th class="right">Paid (₹)</th>
                <th class="right">Overdue</th>
                <th class="right">Disallowance (₹)</th>
                <th class="right">Interest (₹)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['vendor_rows'] as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row['vendor_name'] }}</td>
                <td>{{ $row['vendor_gstin'] ?? '—' }}</td>
                <td>{{ ucfirst($row['category']) }}</td>
                <td class="right">{{ $row['invoice_count'] }}</td>
                <td class="right">{{ number_format($row['total_amount'], 0) }}</td>
                <td class="right">{{ number_format($row['paid_amount'], 0) }}</td>
                <td class="right">{{ $row['overdue_invoices'] }}</td>
                <td class="right @if($row['disallowance_amount'] > 0) danger @endif">
                    {{ number_format($row['disallowance_amount'], 0) }}
                </td>
                <td class="right @if($row['interest_payable'] > 0) danger @endif">
                    {{ number_format($row['interest_payable'], 2) }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" style="text-align:center;padding:16pt;color:#9CA3AF;">
                    No invoices found for this financial year.
                </td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4"><strong>Total</strong></td>
                <td class="right">—</td>
                <td class="right">{{ number_format($report['total_invoice_amount'], 0) }}</td>
                <td class="right">{{ number_format($report['total_paid'], 0) }}</td>
                <td class="right">—</td>
                <td class="right">{{ number_format($report['disallowance_amount'], 0) }}</td>
                <td class="right">{{ number_format($report['total_interest'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <!-- ── Legal notice ── -->
    <div class="notice">
        <p><strong>Legal Disclaimer:</strong> This report is generated for informational purposes under Section 43B(h) of the Income Tax Act, 1961. Interest is computed at 3× the RBI bank rate compounded monthly on overdue outstanding amounts. This report does not constitute tax advice. Consult your Chartered Accountant before filing. Generated on {{ \Carbon\Carbon::parse($report['generated_at'])->format('d M Y H:i') }} IST.</p>
    </div>

    <!-- ── Footer ── -->
    <div class="footer">
        <span>{{ $report['tenant_name'] }} &bull; {{ $report['financial_year'] }} &bull; MSME Payment Risk Tracker</span>
        <span>Generated: {{ \Carbon\Carbon::parse($report['generated_at'])->format('d M Y H:i') }}</span>
    </div>

</div>
</body>
</html>
