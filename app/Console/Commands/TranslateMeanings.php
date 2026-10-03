<?php

namespace App\Console\Commands;

use App\Jobs\TranslateEnglishVocabJob;
use App\Jobs\TranslateMandarinMeaningsJob;
use App\Models\EnglishVocab;
use App\Models\MandarinHskWord;
use Illuminate\Console\Command;

/**
 * Explicit, one-off entry point for backfilling meaning_id via Gemini (see
 * GeminiMeaningTranslator) — deliberately NOT dispatched automatically by the
 * seeders (see MandarinHskSeeder/EnglishVocabSeeder), since this spends AI
 * quota and can take a while on the full ~15k-row dataset.
 *
 * Dispatches onto the queue (QUEUE_CONNECTION=database by default), so a
 * `php artisan queue:work` process must actually be running to process it —
 * this command only enqueues. Progress is logged per batch to
 * storage/logs/laravel.log (`tail -f` it, or `php artisan pail` if
 * installed); safe to re-run any time, since both jobs only ever touch rows
 * still missing meaning_id.
 */
class TranslateMeanings extends Command
{
    protected $signature = 'translate:meanings {--mandarin} {--english}';

    protected $description = 'Queue the Gemini-backed jobs that fill in missing meaning_id/translations->id (run `queue:work` to process them)';

    public function handle(): int
    {
        $mandarin = (bool) $this->option('mandarin');
        $english = (bool) $this->option('english');
        $both = ! $mandarin && ! $english;

        if ($both || $mandarin) {
            $count = MandarinHskWord::whereNull('meaning_id')->count();
            $this->info("Mandarin HSK: {$count} row(s) missing meaning_id.");

            if ($count > 0) {
                TranslateMandarinMeaningsJob::dispatch();
                $this->info('Queued TranslateMandarinMeaningsJob.');
            }
        }

        if ($both || $english) {
            $count = EnglishVocab::whereJsonDoesntContainKey('translations->id')->count();
            $this->info("English CEFR: {$count} row(s) missing translations->id.");

            if ($count > 0) {
                TranslateEnglishVocabJob::dispatch();
                $this->info('Queued TranslateEnglishVocabJob.');
            }
        }

        $this->info('Run `php artisan queue:work` (if not already running) to process the queue, and `tail -f storage/logs/laravel.log` to watch progress.');

        return self::SUCCESS;
    }
}
