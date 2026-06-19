<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function index(Request $request): Response
    {
        $fy = $this->dashboard->resolveFy($request->get('fy'));

        return Inertia::render('Dashboard', [
            'financialYear'       => $fy,
            'availableYears'      => $this->dashboard->availableYears(),
            'stats'               => $this->dashboard->summaryStats($fy),
            'atRiskInvoices'      => $this->dashboard->atRiskInvoices($fy),
            'vendorCounts'        => $this->dashboard->vendorBreakdown(),
            'monthlyTrend'        => $this->dashboard->monthlyTrend($fy),
            'unclassifiedVendors' => $this->dashboard->unclassifiedVendorCount(),
        ]);
    }
}
