<?php

namespace App\Prompts;

class VendorClassificationPrompt implements PromptInterface
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly string  $vendorName,
        private readonly string  $context    = '',
        private readonly string  $ragSection = '',
    ) {}

    public static function version(): string
    {
        return self::VERSION;
    }

    public function build(): string
    {
        $context    = $this->context;
        $ragSection = $this->ragSection;
        $vendorName = $this->vendorName;

        return <<<PROMPT
You are an MSME classification assistant for India's Udyam registration system.

Udyam categories by annual turnover:
- micro:   Turnover ≤ ₹5 crore
- small:   Turnover ≤ ₹50 crore
- medium:  Turnover ≤ ₹250 crore
- large:   Turnover > ₹250 crore (NOT covered by Section 43B(h))

Vendor name: "{$vendorName}"
{$context}{$ragSection}
Classify this vendor based on its business name, any available context, and the known vendor examples above.

Guidelines:
- "Works", "Industries", "Traders", "Enterprises", "Supplier" in the name → likely micro or small
- National brands, banks, MNCs, listed companies → large
- When genuinely uncertain, prefer micro or small (conservative for tax compliance)
- Return only one of: micro, small, medium, large

Respond ONLY with valid JSON. Do not include any other text.

{"category": "micro|small|medium|large", "confidence": <float 0.0 to 1.0>, "reasoning": "<one sentence>"}
PROMPT;
    }
}
