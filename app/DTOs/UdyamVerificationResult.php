<?php

namespace App\DTOs;

use App\Enums\VendorCategory;

final class UdyamVerificationResult
{
    private function __construct(
        public readonly string          $udyamNumber,
        public readonly bool            $verified,
        public readonly bool            $apiAvailable,
        public readonly ?string         $enterpriseName,
        public readonly ?VendorCategory $category,
        public readonly ?string         $registeredAt,
        public readonly ?string         $errorMessage,
    ) {}

    /** API call succeeded and the Udyam number is valid. */
    public static function verified(
        string          $udyamNumber,
        ?string         $enterpriseName,
        ?VendorCategory $category,
        ?string         $registeredAt = null,
    ): self {
        return new self(
            udyamNumber:    $udyamNumber,
            verified:       true,
            apiAvailable:   true,
            enterpriseName: $enterpriseName,
            category:       $category,
            registeredAt:   $registeredAt,
            errorMessage:   null,
        );
    }

    /** API responded but the Udyam number was not found / invalid. */
    public static function notFound(string $udyamNumber): self
    {
        return new self(
            udyamNumber:    $udyamNumber,
            verified:       false,
            apiAvailable:   true,
            enterpriseName: null,
            category:       null,
            registeredAt:   null,
            errorMessage:   'Udyam registration number not found in the government database.',
        );
    }

    /** API key is not configured — cannot verify, user should classify manually. */
    public static function notConfigured(string $udyamNumber): self
    {
        return new self(
            udyamNumber:    $udyamNumber,
            verified:       false,
            apiAvailable:   false,
            enterpriseName: null,
            category:       null,
            registeredAt:   null,
            errorMessage:   'Udyam verification API is not configured. Please classify this vendor manually.',
        );
    }

    /** API call failed (timeout, network error, etc.). */
    public static function failed(string $udyamNumber, string $reason = ''): self
    {
        return new self(
            udyamNumber:    $udyamNumber,
            verified:       false,
            apiAvailable:   true,
            enterpriseName: null,
            category:       null,
            registeredAt:   null,
            errorMessage:   'Verification service temporarily unavailable. ' . $reason,
        );
    }

    public function toArray(): array
    {
        return [
            'udyam_number'    => $this->udyamNumber,
            'verified'        => $this->verified,
            'api_available'   => $this->apiAvailable,
            'enterprise_name' => $this->enterpriseName,
            'category'        => $this->category?->value,
            'category_label'  => $this->category?->label(),
            'registered_at'   => $this->registeredAt,
            'error_message'   => $this->errorMessage,
        ];
    }
}
