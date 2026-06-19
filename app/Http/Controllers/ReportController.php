<?php

namespace App\Http\Controllers;

use App\Exports\VendorExposureExport;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    /**
     * Reports index — shows available financial years.
     */
    public function index(Request $request): InertiaResponse
    {
        $tenant     = $request->user()->tenant;
        $currentFy  = $this->currentFinancialYear();
        $years      = range($currentFy, $currentFy - 4);   // last 5 FYs

        return Inertia::render('Reports/Index', [
            'years'      => $years,
            'currentFy'  => $currentFy,
        ]);
    }

    /**
     * Download PDF — annual 43B(h) disallowance summary.
     */
    public function pdf(Request $request, int $fy): Response
    {
        $tenant = $request->user()->tenant;
        $report = $this->reportService->annualSummary($tenant, $fy);

        $pdf = app('dompdf.wrapper')
            ->loadView('reports.annual-summary', compact('report'))
            ->setPaper('a4', 'landscape');

        $filename = "43Bh-{$tenant->name}-FY{$fy}-" . ($fy + 1) . ".pdf";

        return $pdf->download($filename);
    }

    /**
     * Download Excel — vendor exposure export.
     */
    public function excel(Request $request, int $fy): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $tenant   = $request->user()->tenant;
        $filename = "43Bh-{$tenant->name}-FY{$fy}-" . ($fy + 1) . ".xlsx";

        return Excel::download(
            new VendorExposureExport($tenant, $fy, $this->reportService),
            $filename,
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function currentFinancialYear(): int
    {
        $now = now();
        return $now->month >= 4 ? $now->year : $now->year - 1;
    }
}
