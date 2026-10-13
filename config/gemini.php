<?php

/**
 * Retries for Gemini calls (see App\Support\GeminiRetry). Only a 503 or a
 * connection error/timeout is retried; 400/403/404/429 never are. The key and
 * the main model stay in services.gemini.
 */
$list = fn (string $value): array => array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));

return [
    // Retries after the first attempt (2 = 3 attempts in total).
    'retry_max' => (int) env('GEMINI_RETRY_MAX', 2),

    // Wait before retry 1, 2, ...; the last value repeats when retry_max is larger.
    'retry_delays_seconds' => array_map('floatval', $list((string) env('GEMINI_RETRY_DELAYS', '1,3'))),

    // Random extra wait added to each delay, 0 up to this many milliseconds.
    'retry_jitter_ms' => (int) env('GEMINI_RETRY_JITTER_MS', 250),

    // Hard ceiling for the whole call in seconds: every attempt, every wait and
    // every fallback model together. Each attempt's timeout is cut to what remains.
    'retry_budget_seconds' => (float) env('GEMINI_RETRY_BUDGET_SECONDS', 12),
];
