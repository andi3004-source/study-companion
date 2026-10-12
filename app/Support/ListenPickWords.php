<?php

namespace App\Support;

use App\Services\KataBicaraCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Word pool for "Dengar & Pilih": HSK 1 words that have an Indonesian
 * meaning (the same two tables the Kartu page reads), one row per hanzi,
 * plus the closed object list (ikut_prompt = true) when it exists. Each
 * word carries a 'grup' so distractors only come from the same level.
 * Words without an Indonesian meaning are never used.
 */
class ListenPickWords
{
    public const OPTIONS = 4;

    /** @return Collection<int, array{hanzi: string, pinyin: string, arti: string, grup: string}> */
    public static function all(): Collection
    {
        $hsk = DB::query()->fromSub(
            DB::table('mandarin_words')->select(['hanzi', 'pinyin', 'meaning_id'])->where('hsk_level_old', 1)
                ->unionAll(DB::table('mandarin_hsk_words')->select(['hanzi', 'pinyin', 'meaning_id'])->where('hsk_level_old', 1)),
            'kata'
        )->whereNotNull('meaning_id')->where('meaning_id', '!=', '')->get()
            ->map(fn ($row) => [
                'hanzi' => trim((string) $row->hanzi),
                'pinyin' => trim((string) $row->pinyin),
                'arti' => trim((string) $row->meaning_id),
                'grup' => 'hsk1',
            ]);

        $catalog = app(KataBicaraCatalog::class);
        $benda = $catalog->available()
            ? $catalog->entries()->map(fn (array $entry) => [
                'hanzi' => $entry['hanzi'],
                'pinyin' => $entry['pinyin'],
                'arti' => $entry['indonesia'],
                'grup' => 'benda',
            ])
            : collect();

        return $hsk->concat($benda)
            ->filter(fn (array $word) => $word['hanzi'] !== '' && $word['arti'] !== '')
            ->unique('hanzi')
            ->values();
    }

    /** True when at least one level has enough words with distinct meanings for a full question. */
    public static function enough(): bool
    {
        return self::all()->groupBy('grup')
            ->contains(fn (Collection $words) => $words->pluck('arti')->map(fn ($a) => mb_strtolower($a))->unique()->count() >= self::OPTIONS);
    }
}
