<?php

namespace Database\Seeders;

use App\Models\MandarinWord;
use App\Services\AnswerChecker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Imports the official HSK1-6 dataset (storage/app/seed-data/mandarin_hsk1to6_complete.json).
 *
 * HSK1 is NOT re-inserted: the existing 150 rows in mandarin_words drive the
 * Dashboard's day-by-day curriculum via batch_number/order_in_batch, and their
 * ids are referenced by user_word_progress, so they must keep their id, batch
 * and order. Instead we enrich each matched row in place with the columns the
 * curated data lacks (traditional, radical, frequency, ...) — never its
 * pinyin or meanings, which are curated and more reliable than this dataset
 * (see `php artisan hsk:repair-curriculum` for rows an older version of this
 * seeder already overwrote). 14 of the 150 curated hanzi have no exact match
 * in this dataset (different HSK standard/variant, e.g. "哪儿" vs "哪里") —
 * those rows are left untouched rather than guessed at.
 *
 * HSK2-6 (4,841 words) have no existing curriculum row, so they go into the
 * separate mandarin_hsk_words reference table used for browsing only.
 */
class MandarinHskSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(
            file_get_contents(storage_path('app/seed-data/mandarin_hsk1to6_complete.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->updateHsk1Curriculum($data['1']);
        $this->upsertReference($data);

        // Deliberately NOT dispatched here: translating meaning_id is a
        // separate, explicit, AI-quota-spending operation (see
        // TranslateMandarinMeaningsJob) — run it with
        // `php artisan translate:meanings` when you actually want it, not as
        // a side effect of every re-seed (including every test that seeds
        // this reference data).
    }

    private function updateHsk1Curriculum(array $words): void
    {
        $byHanzi = collect($words)->keyBy('hanzi');

        MandarinWord::where('hsk_level_old', 1)->get()->each(function (MandarinWord $existing) use ($byHanzi) {
            $match = $byHanzi->get($existing->hanzi);

            if (! $match) {
                return;
            }

            // pinyin/pinyin_normalized/meaning_en deliberately stay as curated
            // (database/seeders/data): this dataset picks the wrong homograph
            // for several common words (个 "gě", 都 "Dū, surname Du", 听 "yǐn",
            // ...). Only columns the curated data doesn't have are filled in.
            $existing->update([
                'traditional' => $match['traditional'],
                'radical' => $match['radical'],
                'frequency' => $match['frequency'],
                'classifiers' => $match['classifiers'],
                'hsk_level_new' => $match['hsk_level_new'],
            ]);
        });
    }

    private function upsertReference(array $data): void
    {
        $now = now();
        $checker = new AnswerChecker;

        $rows = LazyCollection::make(function () use ($data, $now, $checker) {
            foreach ($data as $level => $words) {
                if ((int) $level === 1) {
                    continue;
                }

                foreach ($words as $w) {
                    yield [
                        'hanzi' => $w['hanzi'],
                        'traditional' => $w['traditional'],
                        'pinyin' => $w['pinyin'],
                        'pinyin_normalized' => $checker->normalizePinyin($w['pinyin']),
                        'meaning_en' => $w['meaning_en'],
                        'pos' => json_encode($w['pos']),
                        'radical' => $w['radical'],
                        'frequency' => $w['frequency'],
                        'hsk_level_old' => $w['hsk_level_old'],
                        'hsk_level_new' => $w['hsk_level_new'],
                        'classifiers' => json_encode($w['classifiers']),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        });

        $rows->chunk(500)->each(function ($chunk) {
            DB::table('mandarin_hsk_words')->upsert(
                $chunk->all(),
                ['hanzi', 'pinyin'],
                ['traditional', 'pinyin_normalized', 'meaning_en', 'pos', 'radical', 'frequency', 'hsk_level_old', 'hsk_level_new', 'classifiers', 'updated_at']
            );
        });
    }
}
