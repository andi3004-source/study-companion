<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The HSK1 curriculum vocabulary as one space-separated string of hanzi, for
 * the Conversation Partner's level lock (see ConversationService). Hanzi
 * only, no pinyin: cheaper in tokens, and a list written as "hanzi (pinyin)"
 * nudges the model into writing its replies that way too, which the
 * text-to-speech then reads out.
 *
 * mandarin_words can hold the same hanzi more than once (e.g. a database
 * seeded twice: ids 1-150 and 151-300), so each hanzi is taken once, from
 * its lowest-id row, and ordered by that row's batch_number/order_in_batch.
 */
class HskVocabularyPrompt
{
    public static function hsk1List(): string
    {
        // Cheap aggregate per call; the key changes whenever HSK1 rows are
        // added, removed or updated (Eloquent touches updated_at), so a stale
        // list is never served after the data changes that way.
        $stamp = DB::table('mandarin_words')
            ->where('hsk_level_old', 1)
            ->selectRaw('COUNT(*) as total, MAX(id) as max_id, MAX(updated_at) as max_updated_at')
            ->first();

        $key = 'hsk1_vocab_prompt:'.md5("{$stamp->total}|{$stamp->max_id}|{$stamp->max_updated_at}");

        return Cache::remember($key, now()->addDay(), fn () => DB::table('mandarin_words')
            ->whereIn('id', fn ($q) => $q->selectRaw('MIN(id)')
                ->from('mandarin_words')
                ->where('hsk_level_old', 1)
                ->groupBy('hanzi'))
            ->orderBy('batch_number')
            ->orderBy('order_in_batch')
            ->orderBy('id')
            ->pluck('hanzi')
            ->implode(' '));
    }
}
