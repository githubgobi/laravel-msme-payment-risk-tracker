<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Services\InvoiceRiskRecomputer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(
        private readonly InvoiceRiskRecomputer $recomputer,
    ) {}

    public function store(StorePaymentRequest $request, PurchaseInvoice $invoice): RedirectResponse
    {
        DB::transaction(function () use ($request, $invoice) {
            Payment::create([
                'tenant_id'        => $invoice->tenant_id,
                'invoice_id'       => $invoice->id,
                'payment_date'     => $request->validated('payment_date'),
                'amount'           => $request->validated('amount'),
                'payment_mode'     => $request->validated('payment_mode'),
                'reference_number' => $request->validated('reference_number'),
                'notes'            => $request->validated('notes'),
                'created_by'       => auth()->id(),
            ]);

            $newPaidAmount = Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->whereNull('deleted_at')
                ->sum('amount');

            PurchaseInvoice::withoutGlobalScopes()
                ->where('id', $invoice->id)
                ->update(['paid_amount' => $newPaidAmount]);

            $invoice->refresh();
            $this->recomputer->recomputeOne($invoice);
        });

        return back()->with('success', 'Payment recorded.');
    }

    public function destroy(PurchaseInvoice $invoice, Payment $payment): RedirectResponse
    {
        if (! auth()->user()->canManageInvoices()) {
            abort(403);
        }

        // Ensure payment belongs to this invoice and same tenant
        if ($payment->invoice_id !== $invoice->id || $payment->tenant_id !== $invoice->tenant_id) {
            abort(403);
        }

        DB::transaction(function () use ($invoice, $payment) {
            $payment->delete();

            $newPaidAmount = Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->whereNull('deleted_at')
                ->sum('amount');

            PurchaseInvoice::withoutGlobalScopes()
                ->where('id', $invoice->id)
                ->update(['paid_amount' => $newPaidAmount]);

            $invoice->refresh();
            $this->recomputer->recomputeOne($invoice);
        });

        return back()->with('success', 'Payment removed.');
    }
}
