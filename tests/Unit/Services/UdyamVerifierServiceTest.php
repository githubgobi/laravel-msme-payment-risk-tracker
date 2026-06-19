<?php

namespace Tests\Unit\Services;

use App\DTOs\UdyamVerificationResult;
use App\Enums\VendorCategory;
use App\Services\UdyamVerifierService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UdyamVerifierServiceTest extends TestCase
{
    private UdyamVerifierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UdyamVerifierService();
    }

    #[Test]
    public function returns_not_configured_when_api_key_is_missing(): void
    {
        config(['services.surepass.token' => null]);

        $result = $this->service->verify('UDYAM-TN-01-0000001');

        $this->assertFalse($result->verified);
        $this->assertFalse($result->apiAvailable);
        $this->assertStringContainsString('not configured', $result->errorMessage);
    }

    #[Test]
    public function returns_verified_with_micro_category_on_success(): void
    {
        config(['services.surepass.token' => 'test-token']);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data'    => [
                    'enterprise_name'   => 'Arjun Textiles Pvt Ltd',
                    'enterprise_type'   => 'Micro',
                    'date_of_registration' => '2022-01-15',
                ],
            ], 200),
        ]);

        $result = $this->service->verify('UDYAM-TN-01-0000001');

        $this->assertTrue($result->verified);
        $this->assertTrue($result->apiAvailable);
        $this->assertSame('Arjun Textiles Pvt Ltd', $result->enterpriseName);
        $this->assertSame(VendorCategory::Micro, $result->category);
        $this->assertSame('2022-01-15', $result->registeredAt);
    }

    #[Test]
    public function returns_verified_with_small_category(): void
    {
        config(['services.surepass.token' => 'test-token']);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data'    => [
                    'enterprise_name' => 'Rajan Engineering Works',
                    'enterprise_type' => 'Small',
                ],
            ], 200),
        ]);

        $result = $this->service->verify('UDYAM-MH-02-0000002');

        $this->assertSame(VendorCategory::Small, $result->category);
    }

    #[Test]
    public function returns_verified_with_medium_category(): void
    {
        config(['services.surepass.token' => 'test-token']);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data'    => [
                    'enterprise_name' => 'Krishnan Industries',
                    'enterprise_type' => 'medium', // lowercase from API
                ],
            ], 200),
        ]);

        $result = $this->service->verify('UDYAM-KA-05-0000003');

        $this->assertSame(VendorCategory::Medium, $result->category);
    }

    #[Test]
    public function category_is_null_for_unknown_enterprise_type(): void
    {
        config(['services.surepass.token' => 'test-token']);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data'    => [
                    'enterprise_name' => 'Unknown Corp',
                    'enterprise_type' => 'Macro', // not a real type
                ],
            ], 200),
        ]);

        $result = $this->service->verify('UDYAM-DL-01-0000099');

        $this->assertTrue($result->verified);
        $this->assertNull($result->category);
    }

    #[Test]
    public function returns_not_found_on_404_response(): void
    {
        config(['services.surepass.token' => 'test-token']);

        Http::fake(['*' => Http::response([], 404)]);

        $result = $this->service->verify('UDYAM-TN-01-9999999');

        $this->assertFalse($result->verified);
        $this->assertTrue($result->apiAvailable);
        $this->assertStringContainsString('not found', strtolower($result->errorMessage));
    }

    #[Test]
    public function returns_not_found_when_success_is_false(): void
    {
        config(['services.surepass.token' => 'test-token']);

        Http::fake([
            '*' => Http::response(['success' => false, 'message' => 'Invalid ID'], 200),
        ]);

        $result = $this->service->verify('UDYAM-TN-01-9999999');

        $this->assertFalse($result->verified);
        $this->assertTrue($result->apiAvailable);
    }

    #[Test]
    public function returns_failed_on_server_error(): void
    {
        config(['services.surepass.token' => 'test-token']);

        Http::fake(['*' => Http::response([], 500)]);

        $result = $this->service->verify('UDYAM-TN-01-0000001');

        $this->assertFalse($result->verified);
        $this->assertStringContainsString('temporarily unavailable', $result->errorMessage);
    }

    #[Test]
    public function returns_failed_on_network_exception(): void
    {
        config(['services.surepass.token' => 'test-token']);

        Http::fake(['*' => Http::failedConnection()]);

        $result = $this->service->verify('UDYAM-TN-01-0000001');

        $this->assertFalse($result->verified);
    }

    #[Test]
    public function to_array_contains_all_expected_keys(): void
    {
        $result = UdyamVerificationResult::verified('UDYAM-TN-01-0000001', 'Acme Ltd', VendorCategory::Micro, '2023-04-01');

        $arr = $result->toArray();

        $this->assertArrayHasKey('udyam_number', $arr);
        $this->assertArrayHasKey('verified', $arr);
        $this->assertArrayHasKey('api_available', $arr);
        $this->assertArrayHasKey('enterprise_name', $arr);
        $this->assertArrayHasKey('category', $arr);
        $this->assertArrayHasKey('category_label', $arr);
        $this->assertArrayHasKey('registered_at', $arr);
        $this->assertArrayHasKey('error_message', $arr);
        $this->assertSame('micro', $arr['category']);
        $this->assertSame('Micro', $arr['category_label']);
    }

    #[Test]
    public function not_configured_result_has_correct_shape(): void
    {
        $result = UdyamVerificationResult::notConfigured('UDYAM-TN-01-0000001');

        $this->assertFalse($result->verified);
        $this->assertFalse($result->apiAvailable);
        $this->assertNull($result->category);
        $this->assertNotEmpty($result->errorMessage);
    }

    #[Test]
    public function not_found_result_has_correct_shape(): void
    {
        $result = UdyamVerificationResult::notFound('UDYAM-TN-01-0000001');

        $this->assertFalse($result->verified);
        $this->assertTrue($result->apiAvailable);
        $this->assertNull($result->category);
    }
}
