<?php

namespace App\Jobs;

use App\Models\MandarinHskWord;
use App\Services\GeminiMeaningTranslator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fills mandarin_hsk_words.meaning_id from meaning_en via Gemini, batching 75
 * words per API call. Only ever processes rows where meaning_id is still
 * null, so re-running (whether re-dispatched after a failure/timeout, or
 * just later once more HSK words exist) resumes from wherever it left off
 * instead of re-spending quota on rows already done — the "meaning_id is
 * null" filter IS the checkpoint, no separate progress table needed.
 */
class TranslateMandarinMeaningsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BATCH_SIZE = 75;

    // ~65 batches at real Gemini latency can run well past the default 60s
    // job timeout; that would kill an in-progress run for no reason since
    // progress is only ever lost row-by-row, not batch-by-batch.
    public int $timeout = 3600;

    public int $tries = 3;

    /** @return int[] seconds before each retry, for a transient API hiccup mid-run. */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(GeminiMeaningTranslator $translator): void
    {
        if (! $translator->isConfigured()) {
            Log::info('TranslateMandarinMeaningsJob skipped: GEMINI_API_KEY is not set.');

            return;
        }

        $total = MandarinHskWord::whereNull('meaning_id')->count();

        if ($total === 0) {
            Log::info('TranslateMandarinMeaningsJob: nothing to do, every row already has meaning_id.');

            return;
        }

        Log::info("TranslateMandarinMeaningsJob: starting, {$total} row(s) need meaning_id.");

        $done = 0;

        MandarinHskWord::whereNull('meaning_id')
            ->orderBy('id')
            ->chunkById(self::BATCH_SIZE, function ($words) use ($translator, $total, &$done) {
                $words = $words->values();
                $translated = $translator->translateBatch($words->pluck('meaning_en')->all());

                foreach ($words as $i => $word) {
                    if (! empty($translated[$i])) {
                        $word->update(['meaning_id' => $translated[$i]]);
                    }
                }

                $done += $words->count();
                Log::info("TranslateMandarinMeaningsJob: {$done}/{$total} done.");

                // Gemini's free tier rate-limited (429) and 503'd back-to-back
                // batches during a real ~15k-word run; a small pause between
                // batches keeps this run under that ceiling instead of
                // relying purely on GeminiMeaningTranslator's reactive retry.
                // Skipped in tests, which use a fake AiClient and would
                // otherwise burn 5 real seconds per assertion for no reason.
                if (! app()->runningUnitTests()) {
                    sleep(5);
                }
            });

        Log::info('TranslateMandarinMeaningsJob: finished.');
    }
}
