<?php

namespace App\Contracts;

/**
 * Turns a Mandarin conversation transcript (see SessionTranscript) into the
 * "Kesalahan terbanyakmu" error analysis. Bound in AppServiceProvider:
 * Langflow (with Gemini as fallback) when configured, otherwise Gemini.
 */
interface SessionAnalyzer
{
    /**
     * Throws on any failure (network, timeout, unparseable output). The
     * optional 'usage' key carries token counts when the provider reports
     * them; it's logged, never stored (see AnalyzeSessionJob). $sessionId,
     * when given, lets a provider keep its own conversation memory per
     * analysis (Langflow's session_id); providers without one ignore it.
     *
     * 'sumber' names the analyzer that produced the result ('langflow' or
     * 'gemini'), so a fallback result is labelled as such; it is stored.
     *
     * @return array{items: array<int, array{kategori: string, contoh: string, koreksi: string, penjelasan: string}>, catatan: ?string, sumber?: string, usage?: array{tokens_in: ?int, tokens_out: ?int}}
     */
    public function analyze(string $transcript, ?int $sessionId = null): array;

    /** Whether this analyzer can run at all (has its URL/API key). */
    public function isConfigured(): bool;
}
