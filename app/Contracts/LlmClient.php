<?php

namespace App\Contracts;

interface LlmClient
{
    public function generate(string $prompt, array $options = []): ?string;

    public function isAvailable(): bool;

    public function getModel(): string;

    public function getEndpoint(): string;
}
