<?php

namespace Tests\Unit\Services;

use App\Services\OllamaClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OllamaClientTest extends TestCase
{
    private function makeClient(): OllamaClient
    {
        return new OllamaClient(
            endpoint: 'http://localhost:11434',
            model:    'qwen2.5:3b',
            timeout:  10,
        );
    }

    #[Test]
    public function generate_returns_response_text_on_success(): void
    {
        Http::fake([
            '*/api/generate' => Http::response(['response' => '{"category":"micro","confidence":0.9,"reasoning":"small workshop"}'], 200),
        ]);

        $client = $this->makeClient();
        $result = $client->generate('classify this vendor');

        $this->assertSame('{"category":"micro","confidence":0.9,"reasoning":"small workshop"}', $result);
    }

    #[Test]
    public function generate_returns_null_on_http_error(): void
    {
        Http::fake([
            '*/api/generate' => Http::response('model not found', 404),
        ]);

        $client = $this->makeClient();
        $result = $client->generate('classify this vendor');

        $this->assertNull($result);
    }

    #[Test]
    public function generate_returns_null_on_connection_exception(): void
    {
        Http::fake([
            '*/api/generate' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $client = $this->makeClient();
        $result = $client->generate('classify this vendor');

        $this->assertNull($result);
    }

    #[Test]
    public function is_available_returns_true_when_model_listed(): void
    {
        Http::fake([
            '*/api/tags' => Http::response([
                'models' => [
                    ['name' => 'qwen2.5:3b'],
                    ['name' => 'llama3.2:3b'],
                ],
            ], 200),
        ]);

        $client = $this->makeClient();
        $this->assertTrue($client->isAvailable());
    }

    #[Test]
    public function is_available_returns_true_when_model_base_matches(): void
    {
        Http::fake([
            '*/api/tags' => Http::response([
                'models' => [['name' => 'qwen2.5:latest']],
            ], 200),
        ]);

        $client = new OllamaClient('http://localhost:11434', 'qwen2.5:latest', 10);
        $this->assertTrue($client->isAvailable());
    }

    #[Test]
    public function is_available_returns_false_when_model_not_listed(): void
    {
        Http::fake([
            '*/api/tags' => Http::response([
                'models' => [['name' => 'llama3.2:3b']],
            ], 200),
        ]);

        $client = $this->makeClient();
        $this->assertFalse($client->isAvailable());
    }

    #[Test]
    public function is_available_returns_false_on_connection_error(): void
    {
        Http::fake([
            '*/api/tags' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('refused');
            },
        ]);

        $client = $this->makeClient();
        $this->assertFalse($client->isAvailable());
    }

    #[Test]
    public function generate_sends_json_format_and_no_stream(): void
    {
        Http::fake([
            '*/api/generate' => Http::response(['response' => 'ok'], 200),
        ]);

        $client = $this->makeClient();
        $client->generate('test prompt');

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            return $body['format'] === 'json'
                && $body['stream'] === false
                && $body['model']  === 'qwen2.5:3b';
        });
    }
}
