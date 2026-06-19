<?php

namespace App\Services;

use App\DTOs\UdyamVerificationResult;
use App\Enums\VendorCategory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies a Udyam registration number against the Surepass API.
 *
 * Gracefully degrades when:
 *   - API key is not configured (returns notConfigured result)
 *   - API is unreachable (returns failed result)
 *   - Udyam number is not in the government DB (returns notFound result)
 *
 * Configure in .env:
 *   SUREPASS_API_KEY=your_bearer_token
 */
final class UdyamVerifierService
{
    private const API_URL     = 'https://kyc-api.surepass.io/api/v1/corporate/udyam';
    private const TIMEOUT_SEC = 10;

    private const ENTERPRISE_TYPE_MAP = [
        'micro'  => VendorCategory::Micro,
        'small'  => VendorCategory::Small,
        'medium' => VendorCategory::Medium,
    ];

    public function verify(string $udyamNumber): UdyamVerificationResult
    {
        $apiKey = config('services.surepass.token');

        if (! $apiKey) {
            return UdyamVerificationResult::notConfigured($udyamNumber);
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SEC)
                ->withToken($apiKey)
                ->post(self::API_URL, ['id_number' => $udyamNumber]);

            if (! $response->successful()) {
                if ($response->status() === 404) {
                    return UdyamVerificationResult::notFound($udyamNumber);
                }

                return UdyamVerificationResult::failed($udyamNumber, "HTTP {$response->status()}");
            }

            if (! $response->json('success')) {
                return UdyamVerificationResult::notFound($udyamNumber);
            }

            $data           = $response->json('data', []);
            $enterpriseType = strtolower($data['enterprise_type'] ?? '');
            $category       = self::ENTERPRISE_TYPE_MAP[$enterpriseType] ?? null;

            return UdyamVerificationResult::verified(
                udyamNumber:    $udyamNumber,
                enterpriseName: $data['enterprise_name'] ?? null,
                category:       $category,
                registeredAt:   $data['date_of_registration'] ?? null,
            );
        } catch (Throwable $e) {
            Log::warning('Udyam verification API error', [
                'udyam_number' => $udyamNumber,
                'error'        => $e->getMessage(),
            ]);

            return UdyamVerificationResult::failed($udyamNumber, $e->getMessage());
        }
    }
}
