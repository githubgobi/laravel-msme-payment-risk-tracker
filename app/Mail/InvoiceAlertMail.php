<?php

namespace App\Mail;

use App\Enums\AlertType;
use App\Models\PurchaseInvoice;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly float  $balance;
    public readonly int    $daysRemaining;
    public readonly string $daysText;
    public readonly float  $totalExposure;

    public function __construct(
        public readonly PurchaseInvoice $invoice,
        public readonly AlertType       $alertType,
    ) {
        $this->balance      = (float) $invoice->amount - (float) $invoice->paid_amount;
        $this->daysRemaining = (int) Carbon::today()->diffInDays($invoice->effective_deadline, false);
        $this->daysText      = $this->daysRemaining >= 0
            ? "{$this->daysRemaining} day" . ($this->daysRemaining !== 1 ? 's' : '') . ' remaining'
            : abs($this->daysRemaining) . ' day' . (abs($this->daysRemaining) !== 1 ? 's' : '') . ' overdue';
        $this->totalExposure = (float) $invoice->disallowance_amount + (float) $invoice->interest_amount;
    }

    public function envelope(): Envelope
    {
        $vendor  = $this->invoice->vendor?->name ?? 'Unknown Vendor';
        $amount  = '₹' . number_format($this->balance, 0, '.', ',');
        $subject = match($this->alertType) {
            AlertType::T10Warning     => "[43B(h)] 10-Day Warning: {$vendor} — {$amount} due soon",
            AlertType::T3Urgent       => "[43B(h)] URGENT: {$vendor} — {$amount} due in 3 days",
            AlertType::Overdue        => "[43B(h)] OVERDUE: {$vendor} — {$amount} — Tax risk active",
            AlertType::YearEndSummary => "[43B(h)] Year-End Exposure Summary",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.invoice-alert');
    }
}
