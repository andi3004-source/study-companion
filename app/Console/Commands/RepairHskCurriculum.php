<?php

namespace App\Console\Commands;

use App\Models\MandarinWord;
use App\Services\AnswerChecker;
use Illuminate\Console\Command;

/**
 * One-off repair for HSK1 curriculum rows (mandarin_words) whose pinyin,
 * pinyin_normalized and meaning_en were overwritten by an older
 * MandarinHskSeeder from mandarin_hsk1to6_complete.json — a dataset that picks
 * the wrong homograph for several common words (个 "gě", 都 "Dū, surname Du",
 * 听 "yǐn", 冷 "Lěng", ...). Restores those three columns from the curated
 * data the curriculum was originally seeded from (see
 * ReferenceDataSeeder::seedMandarinWords), matched by hanzi.
 *
 * Only ever updates those three columns on rows that actually differ, so
 * id/batch_number/order_in_batch (and therefore user_word_progress) are never
 * touched, and re-running is a no-op once everything matches.
 */
class RepairHskCurriculum extends Command
{
    protected $signature = 'hsk:repair-curriculum {--dry-run : Only print what would change}';

    protected $description = 'Restore curated pinyin/pinyin_normalized/meaning_en on HSK1 curriculum rows (mandarin_words)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $curated = $this->curatedByHanzi();
        $checker = new AnswerChecker;

        $changes = [];
        $unmatched = 0;

        MandarinWord::orderBy('id')->each(function (MandarinWord $word) use ($curated, $checker, &$changes, &$unmatched) {
            $source = $curated[$word->hanzi] ?? null;

            if ($source === null) {
                $unmatched++;

                return;
            }

            $target = [
                'pinyin' => $source['pinyin'],
                'pinyin_normalized' => $checker->normalizePinyin($source['pinyin']),
                'meaning_en' => $source['meaning_en'],
            ];

            $diff = array_filter($target, fn ($value, $column) => $word->{$column} !== $value, ARRAY_FILTER_USE_BOTH);

            if ($diff !== []) {
                $changes[] = [$word, $diff];
            }
        });

        if ($changes === []) {
            $this->info('Nothing to repair: every curriculum row already matches the curated data.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'hanzi', 'column', 'current', 'curated'],
            collect($changes)->flatMap(fn ($change) => collect($change[1])->map(
                fn ($value, $column) => [$change[0]->id, $change[0]->hanzi, $column, $change[0]->{$column}, $value]
            )->values())->all()
        );

        if ($dryRun) {
            $this->info(count($changes).' row(s) would be repaired. Dry run: nothing written.');

            return self::SUCCESS;
        }

        foreach ($changes as [$word, $diff]) {
            $word->update($diff);
        }

        $this->info(count($changes).' row(s) repaired.'.($unmatched > 0 ? " {$unmatched} row(s) have no curated entry and were left untouched." : ''));

        return self::SUCCESS;
    }

    /** @return array<string, array{pinyin: string, meaning_en: string}> */
    private function curatedByHanzi(): array
    {
        $words = $this->loadJson('mandarin_words.json');
        $english = $this->loadJson('mandarin_words_en.json');

        $curated = [];
        foreach ($words as $i => [$hanzi, $pinyin]) {
            $curated[$hanzi] = ['pinyin' => $pinyin, 'meaning_en' => $english[$i]];
        }

        return $curated;
    }

    private function loadJson(string $file): array
    {
        return json_decode(file_get_contents(database_path('seeders/data/'.$file)), true, 512, JSON_THROW_ON_ERROR);
    }
}
