<?php

return [
    /*
     * Feature flag — set LLM_ENABLED=false in .env to disable all AI calls.
     * When disabled the import pipeline and classify endpoints behave exactly
     * as they did before Phase 11: zero performance impact, zero external calls.
     */
    'enabled' => (bool) env('LLM_ENABLED', false),

    /*
     * Ollama HTTP endpoint.  Run `ollama serve` on the same machine.
     * Production: set LLM_ENDPOINT to point at the server running Ollama.
     */
    'endpoint' => env('LLM_ENDPOINT', 'http://localhost:11434'),

    /*
     * Model to use.  Recommended sizes:
     *   qwen2.5:3b  — ~2 GB VRAM, fast, good for structured JSON tasks
     *   qwen2.5:7b  — ~5 GB VRAM, more accurate, slower
     *   llama3.2:3b — alternative if Qwen is unavailable
     */
    'model' => env('LLM_MODEL', 'qwen2.5:3b'),

    /*
     * HTTP timeout in seconds for a single Ollama request.
     * Keep this generous — first inference after model load can take 10-20 s.
     */
    'timeout' => (int) env('LLM_TIMEOUT', 30),

    /*
     * Confidence threshold (0.0 – 1.0) for auto-applying LLM decisions.
     * Below this threshold the result is returned as a suggestion only —
     * a human must confirm before the change is persisted.
     */
    'confidence_threshold' => (float) env('LLM_CONFIDENCE_THRESHOLD', 0.80),

    /*
     * Maximum number of candidate vendors sent to the LLM for fuzzy matching.
     * Sending too many candidates degrades accuracy and increases latency.
     */
    'max_match_candidates' => (int) env('LLM_MAX_MATCH_CANDIDATES', 20),
];
