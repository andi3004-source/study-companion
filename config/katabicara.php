<?php

/**
 * "Kata bicara" word catalog (App\Services\KataBicaraCatalog).
 *
 * Expected file: a JSON array of entries (or {"items": [...]}), each with
 *   id, hanzi, pinyin, inggris, ipa_inggris, cefr_level
 *   nama_untuk_prompt   Indonesian name (falls back to arti_tampil when empty)
 *   ikut_prompt         bool; only true entries are used
 *   tampil              {mandarin, inggris, indonesia} — not used by this page
 * A missing or unreadable file shows "Katalog belum tersedia" — never an error.
 */
return [
    'path' => env('KATABICARA_PATH', storage_path('app/seed-data/daftar_benda_tertutup.json')),

    // How many available words to suggest when a search finds nothing.
    'contoh_jumlah' => 8,
];
