<?php

namespace App\Http\Controllers;

use App\Enums\VendorVerificationSource;
use App\Models\Vendor;
use App\Services\UdyamVerifierService;
use App\Services\VendorClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UdyamVerificationController extends Controller
{
    public function __construct(
        private readonly UdyamVerifierService        $verifier,
        private readonly VendorClassificationService $classifier,
    ) {}

    /**
     * Verify a Udyam number via the external API.
     * Optionally applies the result to a vendor if `vendor_id` is provided.
     *
     * POST /udyam/verify
     *   { udyam_number: string, vendor_id?: int }
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'udyam_number' => ['required', 'string', 'regex:/^UDYAM-[A-Z]{2}-\d{2}-\d{7}$/'],
            'vendor_id'    => ['nullable', 'integer', 'exists:vendors,id'],
        ]);

        $udyamNumber = strtoupper(trim($request->input('udyam_number')));
        $result      = $this->verifier->verify($udyamNumber);

        // Auto-apply the classification if a vendor was specified and API succeeded
        if ($result->verified && $request->filled('vendor_id')) {
            $vendor = Vendor::findOrFail($request->integer('vendor_id'));

            $this->classifier->classify(
                vendor:      $vendor,
                category:    $result->category,
                udyamNumber: $udyamNumber,
                source:      VendorVerificationSource::Api,
                verifiedAt:  now(),
            );
        }

        return response()->json($result->toArray());
    }
}
