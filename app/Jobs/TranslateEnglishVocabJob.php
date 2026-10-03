<?php

namespace App\Jobs;

use App\Models\EnglishVocab;
use App\Services\GeminiMeaningTranslator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fills the 'id' (Indonesian) key inside english_vocab.translations via
 * Gemini, batching 75 words per API call. Only processes rows missing that
 * key, so re-running (whether re-dispatched after a failure/timeout, or
 * later once more vocab exists) resumes from wherever it left off instead of
 * re-spending quota on already-translated entries — the "translations has no
 * 'id' key" filter IS the checkpoint, no separate progress table needed.
 */
class TranslateEnglishVocabJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BATCH_SIZE = 75;

    // ~143 batches at real Gemini latency can run well past the default 60s
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
            Log::info('TranslateEnglishVocabJob skipped: GEMINI_API_KEY is not set.');

            return;
        }

        $total = EnglishVocab::whereJsonDoesntContainKey('translations->id')->count();

        if ($total === 0) {
            Log::info('TranslateEnglishVocabJob: nothing to do, every row already has translations->id.');

            return;
        }

        Log::info("TranslateEnglishVocabJob: starting, {$total} row(s) need translations->id.");

        $done = 0;

        EnglishVocab::whereJsonDoesntContainKey('translations->id')
            ->orderBy('id')
            ->chunkById(self::BATCH_SIZE, function ($entries) use ($translator, $total, &$done) {
                $entries = $entries->values();
                $translated = $translator->translateBatch($entries->pluck('word')->all());

                foreach ($entries as $i => $entry) {
                    if (! empty($translated[$i])) {
                        $entry->update([
                            'translations' => [...$entry->translations, 'id' => $translated[$i]],
                        ]);
                    }
                }

                $done += $entries->count();
                Log::info("TranslateEnglishVocabJob: {$done}/{$total} done.");

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

        Log::info('TranslateEnglishVocabJob: finished.');
    }
}
