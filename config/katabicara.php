<?php

/**
 * "Kata bicara" word catalog (App\Services\KataBicaraCatalog).
 *
 * Expected file: a JSON array of entries (or {"items": [...]}), each with
 *   indonesia, inggris, ipa_inggris, hanzi, pinyin  (strings)
 *   ikut_prompt                                     (bool; only true entries are used)
 * A missing or unreadable file shows "Katalog belum tersedia" — never an error.
 */
return [
    'path' => env('KATABICARA_PATH', storage_path('app/seed-data/daftar_benda_tertutup.json')),

    // How many available words to suggest when a search finds nothing.
    'contoh_jumlah' => 8,
];
