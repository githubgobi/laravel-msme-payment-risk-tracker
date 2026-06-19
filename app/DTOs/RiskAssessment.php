<?php

namespace App\DTOs;

use App\Enums\InvoiceStatus;

final class RiskAssessment
{
    public function __construct(
        public readonly bool          $isSubjectTo43Bh,
        public readonly float         $disallowanceAmount,
        public readonly float         $interestAmount,
        public readonly float         $totalTaxExposure,
        public readonly int           $daysOverdue,
        public readonly int           $monthsOverdue,
        public readonly bool          $isOverdue,
        public readonly bool          $isPaid,
        public readonly string        $financialYear,
        public readonly InvoiceStatus $status,
    ) {}

    public function toArray(): array
    {
        return [
            'is_subject_to_43bh'  => $this->isSubjectTo43Bh,
            'disallowance_amount' => $this->disallowanceAmount,
            'interest_amount'     => $this->interestAmount,
            'total_tax_exposure'  => $this->totalTaxExposure,
            'days_overdue'        => $this->daysOverdue,
            'months_overdue'      => $this->monthsOverdue,
            'is_overdue'          => $this->isOverdue,
            'is_paid'             => $this->isPaid,
            'financial_year'      => $this->financialYear,
            'status'              => $this->status->value,
        ];
    }

    public static function safe(string $financialYear): self
    {
        return new self(
            isSubjectTo43Bh:    false,
            disallowanceAmount: 0.0,
            interestAmount:     0.0,
            totalTaxExposure:   0.0,
            daysOverdue:        0,
            monthsOverdue:      0,
            isOverdue:          false,
            isPaid:             false,
            financialYear:      $financialYear,
            status:             InvoiceStatus::Pending,
        );
    }
}
