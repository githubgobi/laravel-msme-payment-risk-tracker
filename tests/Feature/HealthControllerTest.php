<?php

namespace Tests\Feature;

use App\Services\OllamaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_accessible_without_authentication(): void
    {
        $this->getJson('/health')->assertStatus(200);
    }

    public function test_health_returns_required_keys(): void
    {
        $response = $this->getJson('/health')->assertOk();

        $response->assertJsonStructure([
            'status',
            'version',
            'timestamp',
            'checks' => [
                'database' => ['status'],
                'ollama'   => ['status'],
                'queue'    => ['status', 'driver'],
            ],
        ]);
    }

    public function test_database_check_reports_ok_when_connected(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('status', 'ok');
    }

    public function test_database_check_includes_latency_ms(): void
    {
        $data = $this->getJson('/health')->assertOk()->json();
        $this->assertIsInt($data['checks']['database']['latency_ms'] ?? null);
        $this->assertGreaterThanOrEqual(0, $data['checks']['database']['latency_ms']);
    }

    public function test_ollama_check_reports_disabled_when_llm_disabled(): void
    {
        config(['llm.enabled' => false]);

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('checks.ollama.status', 'disabled');
    }

    public function test_ollama_check_reports_down_when_unreachable(): void
    {
        config(['llm.enabled' => true]);

        $this->mock(OllamaClient::class)
            ->shouldReceive('isAvailable')->once()->andReturn(false);

        $response = $this->getJson('/health');

        $response->assertJsonPath('checks.ollama.status', 'down');
        $response->assertJsonPath('status', 'degraded');
        $response->assertOk(); // 200 even when degraded — only DB down gives 503
    }

    public function test_ollama_check_reports_ok_when_available(): void
    {
        config(['llm.enabled' => true]);

        $this->mock(OllamaClient::class)
            ->shouldReceive('isAvailable')->once()->andReturn(true);

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('checks.ollama.status', 'ok')
            ->assertJsonPath('status', 'ok');
    }

    public function test_queue_check_reports_driver_name(): void
    {
        $driver = config('queue.default');

        $data = $this->getJson('/health')->assertOk()->json();
        $this->assertSame($driver, $data['checks']['queue']['driver']);
    }

    public function test_returns_503_when_database_is_down(): void
    {
        DB::shouldReceive('select')->andThrow(new \Exception('Connection refused'));

        $this->getJson('/health')->assertStatus(503);
    }

    public function test_overall_status_is_down_when_database_fails(): void
    {
        DB::shouldReceive('select')->andThrow(new \Exception('Connection refused'));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('checks.database.status', 'down');
    }

    public function test_timestamp_is_valid_iso8601(): void
    {
        $ts = $this->getJson('/health')->assertOk()->json('timestamp');
        $this->assertNotFalse(
            \DateTime::createFromFormat(\DateTime::ATOM, $ts),
            "Timestamp '{$ts}' is not valid ISO 8601"
        );
    }
}
