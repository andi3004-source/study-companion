<?php

namespace App\Jobs;

use App\Contracts\SessionAnalyzer;
use App\Models\ConversationApiCall;
use App\Models\ConversationSession;
use App\Support\ErrorAnalysis;
use App\Support\SessionTranscript;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Kesalahan terbanyakmu": analyzes a finished Mandarin session's transcript
 * (see SessionAnalyzer) and stores the result on the session. Dispatched by
 * ConversationService::endSession().
 *
 * Idempotent: a session whose analyzed_at is already set is skipped, so a
 * duplicate dispatch never spends a second API call — `analysis:retry-failed`
 * clears analyzed_at first when a failed analysis should run again.
 *
 * Never throws: a failure is reported and stored as analyzed_at set +
 * error_analysis NULL ("tried and failed"), which is what the chat summary
 * and the retry command look for. A success with no mistakes is stored as
 * {"items": [], ...}, never NULL.
 */
class AnalyzeSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Langflow alone may take up to services.langflow.timeout (120s by
    // default) before the Gemini fallback (~30s HTTP timeout) even starts.
    // The queue's retry_after must stay above this (DB_QUEUE_RETRY_AFTER=200).
    public int $timeout = 180;

    // Failures are caught and recorded, not retried — see `analysis:retry-failed`.
    public int $tries = 1;

    public function __construct(public readonly int $sessionId) {}

    public function handle(SessionAnalyzer $analyzer): void
    {
        $session = ConversationSession::find($this->sessionId);

        if (! $session || $session->lang !== 'mandarin' || $session->analyzed_at !== null) {
            return;
        }

        if (! $session->turns()->where('role', 'user')->exists()) {
            return;
        }

        try {
            $result = $analyzer->analyze(SessionTranscript::build($session), $session->id);
        } catch (Throwable $e) {
            report($e);
            $session->update(['error_analysis' => null, 'analyzed_at' => now()]);
            Log::info("AnalyzeSessionJob: session {$session->id} analysis failed.");

            return;
        }

        $usage = $result['usage'] ?? null;

        // normalize() keeps only items/catatan, so 'usage' is never stored.
        $session->update(['error_analysis' => ErrorAnalysis::normalize($result), 'analyzed_at' => now()]);

        if (is_array($usage) && (isset($usage['tokens_in']) || isset($usage['tokens_out']))) {
            ConversationApiCall::create([
                'session_id' => $session->id,
                'tokens_in' => $usage['tokens_in'] ?? null,
                'tokens_out' => $usage['tokens_out'] ?? null,
            ]);
        }
    }
}
