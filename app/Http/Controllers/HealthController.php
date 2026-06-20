<?php

namespace App\Http\Controllers;

use App\Services\OllamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __construct(private readonly OllamaClient $ollama) {}

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'ollama'   => $this->checkOllama(),
            'queue'    => $this->checkQueue(),
        ];

        $overallStatus = $this->resolveStatus($checks);

        return response()->json([
            'status'    => $overallStatus,
            'version'   => config('app.version', '1.0.0'),
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
        ], $overallStatus === 'down' ? 503 : 200);
    }

    private function checkDatabase(): array
    {
        $start = hrtime(true);
        try {
            DB::select('SELECT 1');
            $latency = (int) ((hrtime(true) - $start) / 1_000_000);
            return ['status' => 'ok', 'latency_ms' => $latency];
        } catch (Throwable $e) {
            return ['status' => 'down', 'error' => 'Database unreachable'];
        }
    }

    private function checkOllama(): array
    {
        if (! config('llm.enabled')) {
            return ['status' => 'disabled', 'model' => config('llm.model')];
        }

        $start     = hrtime(true);
        $available = $this->ollama->isAvailable();
        $latency   = (int) ((hrtime(true) - $start) / 1_000_000);

        return [
            'status'     => $available ? 'ok' : 'down',
            'latency_ms' => $latency,
            'model'      => config('llm.model'),
            'endpoint'   => config('llm.endpoint'),
        ];
    }

    private function checkQueue(): array
    {
        $driver = config('queue.default');

        try {
            if ($driver === 'database') {
                $pending = DB::table(config('queue.connections.database.table', 'jobs'))->count();
                $failed  = DB::table('failed_jobs')->count();
                $status  = $pending > 500 ? 'degraded' : 'ok';
                return compact('status', 'driver', 'pending', 'failed');
            }

            if ($driver === 'redis') {
                $queue   = config('queue.connections.redis.queue', 'default');
                $pending = app('redis')->llen("queues:{$queue}");
                $status  = $pending > 500 ? 'degraded' : 'ok';
                return compact('status', 'driver', 'pending');
            }

            return ['status' => 'ok', 'driver' => $driver];
        } catch (Throwable) {
            return ['status' => 'degraded', 'driver' => $driver, 'error' => 'Queue check failed'];
        }
    }

    private function resolveStatus(array $checks): string
    {
        if (($checks['database']['status'] ?? '') === 'down') {
            return 'down';
        }

        foreach ($checks as $check) {
            if (in_array($check['status'] ?? '', ['down', 'degraded'], true)) {
                return 'degraded';
            }
        }

        return 'ok';
    }
}
