<?php

namespace App\Prompts;

class FuzzyMatchPrompt implements PromptInterface
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly string $importedName,
        private readonly string $candidateList,
    ) {}

    public static function version(): string
    {
        return self::VERSION;
    }

    public function build(): string
    {
        $importedName  = $this->importedName;
        $candidateList = $this->candidateList;

        return <<<PROMPT
You are a vendor deduplication assistant for an Indian accounts payable system.

The import file contains a vendor named: "{$importedName}"

Existing vendors in the system:
{$candidateList}

Which existing vendor best matches the imported name? Consider:
- Common abbreviations: Pvt=Private, Ltd=Limited, Co=Company, Mfg=Manufacturing, Ind=Industries
- Spelling variations and transliteration differences
- Same business entity, different name format

Respond ONLY with valid JSON. Do not include any other text.
If no existing vendor is a plausible match, return vendor_id as null.

{"vendor_id": <integer id or null>, "confidence": <float 0.0 to 1.0>, "reasoning": "<one sentence>"}
PROMPT;
    }
}
