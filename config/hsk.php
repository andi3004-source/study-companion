<?php

return [

    /*
     * Hand-checked HSK2-3 corrections (hanzi, pinyin, indonesian, hsk_level,
     * pinyin_asli, ...) for entries where mandarin_hsk1to6_complete.json picked
     * the wrong homograph. Read by MandarinHskSeeder and
     * `php artisan hsk:apply-corrections`; a missing file means "no corrections".
     */
    'corrections_path' => env('HSK_CORRECTIONS_PATH', storage_path('app/seed-data/hsk1to3_kosakata_id.json')),

    /*
     * Conversation Partner level lock: Mandarin sessions get the HSK1
     * vocabulary list (see HskVocabularyPrompt) plus an instruction to reply
     * only with those words. Costs extra input tokens on every Mandarin
     * message (see `php artisan hsk:prompt-stats`); false turns it off.
     */
    'level_lock' => (bool) env('HSK_LEVEL_LOCK', true),

];
