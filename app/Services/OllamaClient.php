<?php

namespace App\Services;

use App\Contracts\LlmClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Ollama HTTP API.
 *
 * All methods return null on failure — callers must handle null gracefully.
 * Exceptions are caught and logged here so the import pipeline is never
 * interrupted by LLM availability issues.
 */
class OllamaClient implements LlmClient
{
    private string $endpoint;
    private string $model;
    private int    $timeout;

    public function __construct(
        string $endpoint,
        string $model,
        int    $timeout,
    ) {
        $this->endpoint = rtrim($endpoint, '/');
        $this->model    = $model;
        $this->timeout  = $timeout;
    }

    /**
     * Send a single prompt and return the text response.
     * Uses /api/generate (non-streaming).
     *
     * @param  array<string,mixed>  $options  Extra Ollama options (temperature, seed, etc.)
     */
    public function generate(string $prompt, array $options = []): ?string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->endpoint}/api/generate", array_merge([
                    'model'  => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'format' => 'json',
                    'options' => array_merge([
                        'temperature' => 0.0,
                        'seed'        => 42,
                    ], $options['options'] ?? []),
                ], $options));

            if (! $response->successful()) {
                Log::warning('Ollama generate failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json('response');
        } catch (ConnectionException $e) {
            Log::warning('Ollama unreachable', ['error' => $e->getMessage()]);
            return null;
        } catch (\Throwable $e) {
            Log::error('OllamaClient::generate exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Check whether the Ollama server is reachable and the configured model
     * is available.  Used for health checks and admin UI status display.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->endpoint}/api/tags");

            if (! $response->successful()) {
                return false;
            }

            $models = collect($response->json('models') ?? [])
                ->pluck('name');

            // Accept exact match or base model without tag suffix
            $base = explode(':', $this->model)[0];

            return $models->contains(fn ($m) =>
                $m === $this->model || str_starts_with($m, $base . ':')
            );
        } catch (\Throwable) {
            return false;
        }
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }
}
