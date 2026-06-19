<?php

namespace App\DTOs;

final class RowImportResult
{
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_SKIPPED  = 'skipped';
    public const STATUS_FAILED   = 'failed';

    private function __construct(
        public readonly string $status,
        public readonly int    $rowNumber,
        public readonly string $invoiceNumber,
        public readonly string $message,
    ) {}

    public static function imported(int $row, string $invoiceNumber): self
    {
        return new self(self::STATUS_IMPORTED, $row, $invoiceNumber, 'Imported successfully');
    }

    public static function skipped(int $row, string $invoiceNumber, string $reason): self
    {
        return new self(self::STATUS_SKIPPED, $row, $invoiceNumber, $reason);
    }

    public static function failed(int $row, string $invoiceNumber, string $reason): self
    {
        return new self(self::STATUS_FAILED, $row, $invoiceNumber, $reason);
    }

    public function toArray(): array
    {
        return [
            'status'         => $this->status,
            'row'            => $this->rowNumber,
            'invoice_number' => $this->invoiceNumber,
            'message'        => $this->message,
        ];
    }
}
