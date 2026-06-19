<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'total_at_risk'           => 0,
                'due_this_week'           => 0,
                'projected_disallowance'  => 0,
                'projected_interest'      => 0,
            ],
            'atRiskInvoices' => [],
            'vendorCounts'   => [
                'micro'        => 0,
                'small'        => 0,
                'medium'       => 0,
                'unclassified' => 0,
            ],
        ]);
    }
}
