<?php

namespace App\Prompts;

interface PromptInterface
{
    public static function version(): string;
    public function build(): string;
}
