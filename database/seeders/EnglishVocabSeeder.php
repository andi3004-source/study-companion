<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Imports the 10,674-entry CEFR vocab dataset (~12MB) into english_vocab, a
 * standalone dictionary table unrelated to the existing ielts_vocab curriculum
 * table. json_decode still needs the whole array in memory once (PHP has no
 * built-in streaming JSON parser), so we raise memory_limit for this run
 * rather than hand-rolling a tokenizer; inserts themselves are chunked to
 * stay under SQLite's per-query bound variable limit.
 */
class EnglishVocabSeeder extends Seeder
{
    public function run(): void
    {
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $entries = json_decode(
                file_get_contents(storage_path('app/seed-data/english_vocab_cefr_10674.json')),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $now = now();

            $rows = array_map(fn (array $e) => [
                'external_id' => $e['id'],
                'word' => $e['word'],
                'pos' => $e['pos'] ?? null,
                'definition' => $e['definition'] ?? null,
                'hint' => $e['hint'] ?? null,
                'cefr_level' => $e['level'],
                'categories' => json_encode($e['cats'] ?? []),
                'examples' => json_encode($e['examples'] ?? []),
                'translations' => json_encode($e['translations'] ?? []),
                'ipa' => $e['ipa'] ?? null,
                'synonyms' => json_encode($e['synonyms'] ?? []),
                'created_at' => $now,
                'updated_at' => $now,
            ], $entries);

            unset($entries);

            foreach (array_chunk($rows, 50) as $chunk) {
                // 'translations' is deliberately excluded from the update list: a re-run
                // must not clobber the 'id' key the translation job fills in asynchronously.
                DB::table('english_vocab')->upsert(
                    $chunk,
                    ['external_id'],
                    ['word', 'pos', 'definition', 'hint', 'cefr_level', 'categories', 'examples', 'ipa', 'synonyms', 'updated_at']
                );
            }
        } finally {
            ini_set('memory_limit', $previousLimit);
        }

        // Deliberately NOT dispatched here: translating translations->id is a
        // separate, explicit, AI-quota-spending operation (see
        // TranslateEnglishVocabJob) — run it with
        // `php artisan translate:meanings` when you actually want it, not as
        // a side effect of every re-seed (including every test that seeds
        // this reference data).
    }
}
