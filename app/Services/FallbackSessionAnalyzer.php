<?php

namespace App\Services;

use App\Contracts\SessionAnalyzer;
use Throwable;

/**
 * Tries $primary (Langflow) and, on any failure — HTTP error, timeout,
 * missing/garbled output — reports it and asks $fallback (Gemini) instead,
 * so a Langflow outage costs one report, not the learner's analysis.
 */
class FallbackSessionAnalyzer implements SessionAnalyzer
{
    public function __construct(
        private readonly SessionAnalyzer $primary,
        private readonly SessionAnalyzer $fallback,
    ) {}

    public function isConfigured(): bool
    {
        return $this->primary->isConfigured() || $this->fallback->isConfigured();
    }

    public function analyze(string $transcript, ?int $sessionId = null): array
    {
        try {
            return $this->primary->analyze($transcript, $sessionId);
        } catch (Throwable $e) {
            report($e);

            return $this->fallback->analyze($transcript, $sessionId);
        }
    }
}
