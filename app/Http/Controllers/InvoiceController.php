<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMode;
use App\Enums\VendorCategory;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\PurchaseInvoice;
use App\Models\Vendor;
use App\Services\InvoiceRiskRecomputer;
use App\Services\MsmeDeadlineEngine;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceRiskRecomputer $recomputer,
        private readonly MsmeDeadlineEngine    $engine,
    ) {}

    public function index(Request $request): Response
    {
        $user   = auth()->user();
        $tenant = $user->tenant;

        $query = PurchaseInvoice::with('vendor:id,name,category')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('financial_year'), fn ($q) => $q->where('financial_year', $request->financial_year))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->vendor_id))
            ->when($request->filled('search'), fn ($q) => $q->where('invoice_number', 'like', "%{$request->search}%"))
            ->orderByRaw("CASE WHEN status = 'overdue' THEN 0 ELSE 1 END")
            ->orderBy('effective_deadline')
            ->paginate(25)
            ->withQueryString();

        $invoices = $query->through(fn ($inv) => $this->formatInvoice($inv));

        // Summary for the current filter set
        $allInvoices = PurchaseInvoice::selectRaw("
            SUM(CASE WHEN status IN ('pending','partial','overdue') THEN (amount - paid_amount) ELSE 0 END) as at_risk_balance,
            SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
            SUM(CASE WHEN status IN ('pending','partial') THEN 1 ELSE 0 END) as pending_count,
            SUM(disallowance_amount + interest_amount) as total_exposure
        ")->first();

        $vendors = Vendor::select('id', 'name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Invoices/Index', [
            'invoices'        => $invoices,
            'filters'         => $request->only(['status', 'financial_year', 'vendor_id', 'search']),
            'vendors'         => $vendors,
            'financial_years' => $this->availableYears($tenant->id),
            'statuses'        => collect(InvoiceStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values(),
            'summary'         => [
                'at_risk_balance' => (float) ($allInvoices->at_risk_balance ?? 0),
                'overdue_count'   => (int)   ($allInvoices->overdue_count ?? 0),
                'pending_count'   => (int)   ($allInvoices->pending_count ?? 0),
                'total_exposure'  => (float) ($allInvoices->total_exposure ?? 0),
            ],
            'canManage'       => $user->canManageInvoices(),
        ]);
    }

    public function show(PurchaseInvoice $invoice): Response
    {
        $invoice->load([
            'vendor:id,name,category,gstin,udyam_number',
            'importBatch:id,original_filename,source,completed_at',
            'payments' => fn ($q) => $q->whereNull('deleted_at')->orderBy('payment_date'),
        ]);

        $daysToDeadline = Carbon::today()->diffInDays(Carbon::parse($invoice->effective_deadline), false);

        return Inertia::render('Invoices/Show', [
            'invoice'      => $this->formatInvoiceDetail($invoice, $daysToDeadline),
            'paymentModes' => collect(PaymentMode::cases())->map(fn ($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ])->values(),
            'canManage'    => auth()->user()->canManageInvoices(),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, PurchaseInvoice $invoice): RedirectResponse
    {
        $data = $request->validated();

        // When agreement_exists is toggled, recalculate the effective deadline
        if (array_key_exists('agreement_exists', $data) && $data['agreement_exists'] !== $invoice->agreement_exists) {
            $newDeadline = $this->engine->computeDeadline(
                Carbon::parse($invoice->invoice_date),
                (bool) $data['agreement_exists'],
            );
            $data['effective_deadline'] = $newDeadline->toDateString();

            PurchaseInvoice::withoutGlobalScopes()->where('id', $invoice->id)->update($data);
            $invoice->refresh();
            $this->recomputer->recomputeOne($invoice);
        } else {
            $invoice->update($data);
        }

        return back()->with('success', 'Invoice updated.');
    }

    public function destroy(PurchaseInvoice $invoice): RedirectResponse
    {
        if (! auth()->user()->canManageInvoices()) {
            abort(403);
        }

        $hasPayments = $invoice->payments()->whereNull('deleted_at')->exists();
        if ($hasPayments) {
            return back()->withErrors(['general' => 'Cannot delete an invoice with recorded payments.']);
        }

        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatInvoice(PurchaseInvoice $inv): array
    {
        $deadline    = Carbon::parse($inv->effective_deadline);
        $daysLeft    = (int) Carbon::today()->diffInDays($deadline, false);
        $taxExposure = (float) $inv->disallowance_amount + (float) $inv->interest_amount;

        return [
            'id'               => $inv->id,
            'invoice_number'   => $inv->invoice_number,
            'invoice_date'     => $inv->invoice_date?->toDateString(),
            'vendor_name'      => $inv->vendor?->name,
            'vendor_category'  => $inv->vendor_category_snapshot?->value,
            'amount'           => (float) $inv->amount,
            'paid_amount'      => (float) $inv->paid_amount,
            'balance'          => (float) $inv->amount - (float) $inv->paid_amount,
            'effective_deadline' => $deadline->toDateString(),
            'days_to_deadline' => $daysLeft,
            'status'           => $inv->status?->value,
            'status_label'     => $inv->status?->label(),
            'disallowance_amount' => (float) $inv->disallowance_amount,
            'interest_amount'  => (float) $inv->interest_amount,
            'tax_exposure'     => $taxExposure,
            'financial_year'   => $inv->financial_year,
            'agreement_exists' => (bool) $inv->agreement_exists,
        ];
    }

    private function formatInvoiceDetail(PurchaseInvoice $inv, int $daysToDeadline): array
    {
        return array_merge($this->formatInvoice($inv), [
            'narration'    => $inv->narration,
            'vendor'       => $inv->vendor ? [
                'id'           => $inv->vendor->id,
                'name'         => $inv->vendor->name,
                'category'     => $inv->vendor->category?->value,
                'gstin'        => $inv->vendor->gstin,
                'udyam_number' => $inv->vendor->udyam_number,
            ] : null,
            'import_batch' => $inv->importBatch ? [
                'id'                => $inv->importBatch->id,
                'original_filename' => $inv->importBatch->original_filename,
                'source'            => $inv->importBatch->source,
                'completed_at'      => $inv->importBatch->completed_at?->toDateString(),
            ] : null,
            'payments'     => $inv->payments->map(fn ($p) => [
                'id'               => $p->id,
                'payment_date'     => $p->payment_date?->toDateString(),
                'amount'           => (float) $p->amount,
                'payment_mode'     => $p->payment_mode?->value,
                'payment_mode_label' => $p->payment_mode?->label(),
                'reference_number' => $p->reference_number,
                'notes'            => $p->notes,
            ])->values(),
            'last_computed_at' => $inv->last_computed_at?->toDateTimeString(),
        ]);
    }

    private function availableYears(int $tenantId): array
    {
        $years = PurchaseInvoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->select('financial_year')
            ->distinct()
            ->orderBy('financial_year', 'desc')
            ->pluck('financial_year')
            ->toArray();

        // Always include current FY
        $engine     = new MsmeDeadlineEngine();
        $currentFy  = $engine->computeFinancialYear(Carbon::today());
        if (! in_array($currentFy, $years)) {
            array_unshift($years, $currentFy);
        }

        return $years;
    }
}
