<?php

namespace App\Console\Commands;

use App\Models\MandarinHskWord;
use App\Services\AnswerChecker;
use App\Support\HskCorrections;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Applies the hand-checked HSK2-3 corrections (see HskCorrections) to
 * mandarin_hsk_words: each entry's row is found by (hanzi, pinyin_asli),
 * regardless of hsk_level, and gets the corrected pinyin/pinyin_normalized,
 * the Indonesian meaning_id and data_status='corrected_draft'.
 *
 * mandarin_hsk_words is unique on (hanzi, pinyin), so a correction whose new
 * pinyin is already taken by another row would collide. Those are listed and
 * skipped — nothing is ever deleted here; resolve them by hand.
 *
 * Idempotent: an already-corrected row is found again by its corrected
 * pinyin and is only written to if something still differs.
 */
class ApplyHskCorrections extends Command
{
    protected $signature = 'hsk:apply-corrections {--dry-run : Only print what would change}';

    protected $description = 'Apply the HSK2-3 pinyin/meaning_id corrections file to mandarin_hsk_words';

    public function handle(): int
    {
        if (! HskCorrections::exists()) {
            $this->error('Corrections file not found: '.(HskCorrections::path() ?: '(HSK_CORRECTIONS_PATH is empty)'));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $checker = new AnswerChecker;

        $rows = MandarinHskWord::orderBy('id')->get()
            ->keyBy(fn (MandarinHskWord $w) => HskCorrections::key($w->hanzi, $w->pinyin))
            ->all();

        $matched = 0;
        $updates = [];
        $collisions = [];
        $notFound = [];

        foreach (HskCorrections::byOriginalKey() as $originalKey => $c) {
            $correctedKey = HskCorrections::key($c['hanzi'], $c['pinyin']);
            $row = $rows[$originalKey] ?? null;

            if ($row === null && ($rows[$correctedKey]->data_status ?? null) === HskCorrections::STATUS) {
                $row = $rows[$correctedKey]; // corrected on an earlier run
            }

            if ($row === null) {
                $notFound[] = [$c['hanzi'], $c['pinyin_asli'], $c['pinyin'], $c['hsk_level']];

                continue;
            }

            $matched++;
            $taken = $rows[$correctedKey] ?? null;

            if ($taken !== null && $taken->id !== $row->id) {
                $collisions[] = [$row->id, $c['hanzi'], $c['pinyin_asli'], $c['pinyin'], $taken->id];

                continue;
            }

            $target = [
                'pinyin' => $c['pinyin'],
                'pinyin_normalized' => $checker->normalizePinyin($c['pinyin']),
                'meaning_id' => $c['indonesian'],
                'data_status' => HskCorrections::STATUS,
            ];

            $diff = array_filter($target, fn ($value, $column) => $row->{$column} !== $value, ARRAY_FILTER_USE_BOTH);

            if ($diff === []) {
                continue;
            }

            $updates[] = [$row, $diff];

            // Keep the lookup in sync so a later entry in this same run sees
            // this row under its new pinyin (and can collide with it).
            unset($rows[HskCorrections::key($row->hanzi, $row->pinyin)]);
            $rows[$correctedKey] = $row;
        }

        if ($updates !== []) {
            $this->table(
                ['id', 'hanzi', 'HSK', 'pinyin', 'meaning_id'],
                array_map(fn ($u) => [
                    $u[0]->id,
                    $u[0]->hanzi,
                    $u[0]->hsk_level_old,
                    $u[0]->pinyin.' → '.($u[1]['pinyin'] ?? $u[0]->pinyin),
                    ($u[0]->meaning_id ?? '∅').' → '.($u[1]['meaning_id'] ?? $u[0]->meaning_id),
                ], $updates)
            );
        }

        if ($collisions !== []) {
            $this->warn('Collisions (skipped, corrected pinyin already used by another row — nothing deleted):');
            $this->table(['id', 'hanzi', 'pinyin_asli', 'corrected pinyin', 'taken by id'], $collisions);
        }

        if ($notFound !== []) {
            $this->warn('Not found in mandarin_hsk_words:');
            $this->table(['hanzi', 'pinyin_asli', 'corrected pinyin', 'hsk_level'], $notFound);
        }

        if (! $dryRun) {
            DB::transaction(function () use ($updates) {
                foreach ($updates as [$row, $diff]) {
                    $row->update($diff);
                }
            });
        }

        $this->info(sprintf(
            'Matched: %d, %s: %d, collisions: %d, not found: %d.%s',
            $matched,
            $dryRun ? 'would update' : 'updated',
            count($updates),
            count($collisions),
            count($notFound),
            $dryRun ? ' Dry run: nothing written.' : ''
        ));

        return self::SUCCESS;
    }
}
