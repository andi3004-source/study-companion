<?php

return [

    /*
     * Hand-checked HSK2-3 corrections (hanzi, pinyin, indonesian, hsk_level,
     * pinyin_asli, ...) for entries where mandarin_hsk1to6_complete.json picked
     * the wrong homograph. Read by MandarinHskSeeder and
     * `php artisan hsk:apply-corrections`; a missing file means "no corrections".
     */
    'corrections_path' => env('HSK_CORRECTIONS_PATH', storage_path('app/seed-data/hsk1to3_kosakata_id.json')),

];
