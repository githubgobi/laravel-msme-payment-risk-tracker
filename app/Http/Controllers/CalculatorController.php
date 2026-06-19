<?php

namespace App\Http\Controllers;

use App\DTOs\RiskAssessment;
use App\Enums\PaymentMode;
use App\Enums\VendorCategory;
use App\Services\MsmeDeadlineEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalculatorController extends Controller
{
    public function __construct(
        private readonly MsmeDeadlineEngine $engine,
    ) {}

    public function index(): Response
    {
        $rbiRate = auth()->user()?->tenant?->rbi_bank_rate ?? MsmeDeadlineEngine::DEFAULT_RBI_BANK_RATE;

        return Inertia::render('Calculator/Index', [
            'vendorCategories' => collect(VendorCategory::cases())->map(fn ($c) => [
                'value'           => $c->value,
                'label'           => $c->label(),
                'subject_to_43bh' => $c->isSubjectTo43Bh(),
            ])->values(),
            'defaultBankRate'  => (float) $rbiRate,
        ]);
    }

    public function compute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_date'    => ['required', 'date'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'agreement_exists' => ['required', 'boolean'],
            'vendor_category' => ['required', 'string', 'in:' . implode(',', array_column(VendorCategory::cases(), 'value'))],
            'bank_rate'       => ['required', 'numeric', 'min:1', 'max:25'],
            'paid_amount'     => ['nullable', 'numeric', 'min:0'],
            'as_of'           => ['nullable', 'date'],
        ]);

        $invoiceDate     = Carbon::parse($validated['invoice_date']);
        $amount          = (float) $validated['amount'];
        $paidAmount      = (float) ($validated['paid_amount'] ?? 0);
        $agreementExists = (bool) $validated['agreement_exists'];
        $vendorCategory  = VendorCategory::from($validated['vendor_category']);
        $bankRate        = (float) $validated['bank_rate'];
        $asOf            = isset($validated['as_of']) ? Carbon::parse($validated['as_of']) : Carbon::today();

        $effectiveDeadline = $this->engine->computeDeadline($invoiceDate, $agreementExists);
        $financialYear     = $this->engine->computeFinancialYear($invoiceDate);
        $deadlineDays      = $agreementExists ? MsmeDeadlineEngine::AGREEMENT_DAYS : MsmeDeadlineEngine::NO_AGREEMENT_DAYS;

        $assessment = $this->engine->assess(
            invoiceDate:    $invoiceDate,
            amount:         $amount,
            paidAmount:     $paidAmount,
            agreementExists: $agreementExists,
            vendorCategory: $vendorCategory,
            bankRate:       $bankRate,
            asOf:           $asOf,
            financialYear:  $financialYear,
        );

        $totalExposure    = $assessment->disallowanceAmount + $assessment->interestAmount;
        $effectiveTaxRate = $amount > 0 ? round($totalExposure / $amount * 100, 2) : 0;
        $daysToDeadline   = (int) $asOf->diffInDays($effectiveDeadline, false);

        return response()->json([
            'effective_deadline'   => $effectiveDeadline->toDateString(),
            'deadline_days'        => $deadlineDays,
            'financial_year'       => $financialYear,
            'status'               => $assessment->status->value,
            'status_label'         => $assessment->status->label(),
            'days_to_deadline'     => $daysToDeadline,
            'days_overdue'         => $assessment->daysOverdue,
            'is_subject_to_43bh'   => $assessment->isSubjectTo43Bh,
            'disallowance_amount'  => $assessment->disallowanceAmount,
            'interest_amount'      => $assessment->interestAmount,
            'total_exposure'       => $totalExposure,
            'effective_tax_rate'   => $effectiveTaxRate,
            'annual_interest_rate' => round($bankRate * MsmeDeadlineEngine::INTEREST_MULTIPLIER, 2),
            'balance'              => round($amount - $paidAmount, 2),
        ]);
    }
}
