@component('mail::message')

@if($alertType->value === 'overdue')
# ❌ Payment Overdue — Tax Risk Active
@elseif($alertType->value === 't3_urgent')
# 🚨 Urgent: Payment Due in 3 Days
@elseif($alertType->value === 't10_warning')
# ⚠️ 10-Day Payment Warning
@else
# 📊 43B(h) Year-End Summary
@endif

**{{ $alertType->label() }}** — Section 43B(h) compliance alert for your organisation.

---

## Invoice Details

| Field | Value |
|:---|:---|
| **Vendor** | {{ $invoice->vendor?->name ?? '—' }} |
| **Invoice #** | {{ $invoice->invoice_number }} |
| **Invoice Date** | {{ $invoice->invoice_date->format('d M Y') }} |
| **Invoice Amount** | ₹{{ number_format($invoice->amount, 2) }} |
| **Amount Paid** | ₹{{ number_format($invoice->paid_amount, 2) }} |
| **Balance Due** | **₹{{ number_format($balance, 2) }}** |
| **Payment Deadline** | {{ $invoice->effective_deadline->format('d M Y') }} |
| **Status** | {{ $daysText }} |

---

## Tax Risk (Section 43B(h))

| | Amount |
|:---|:---|
| **Disallowance** | ₹{{ number_format($invoice->disallowance_amount, 2) }} |
| **Compound Interest** | ₹{{ number_format($invoice->interest_amount, 2) }} |
| **Total Tax Exposure** | **₹{{ number_format($totalExposure, 2) }}** |

@if($invoice->agreement_exists)
> Payment agreement exists — 45-day deadline applies.
@else
> No written agreement — 15-day deadline applies.
@endif

---

@component('mail::panel')
**Action Required:** Pay ₹{{ number_format($balance, 2) }} to {{ $invoice->vendor?->name ?? 'this vendor' }}
before {{ $invoice->effective_deadline->format('d M Y') }} to avoid tax disallowance
under Section 43B(h) of the Income Tax Act.
@endcomponent

@component('mail::button', ['url' => config('app.url') . '/invoices', 'color' => 'red'])
View Invoice in Dashboard
@endcomponent

This is an automated alert from your **MSME Payment Risk Tracker**.
Manage alert settings in your dashboard.

Thanks,
{{ config('app.name') }}

@endcomponent
