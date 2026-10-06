<?php

return [

    /*
     * "Pemburu Kesalahan" question bank: a JSON array of
     * {id, kategori, salah, benar, arti_id, penjelasan, hanzi_di_luar_hsk1, status}.
     * Read directly by App\Support\ErrorGameItems — no table. A missing file
     * means the game shows "Data game belum tersedia".
     */
    'items_path' => env('GAME_ITEMS_PATH', storage_path('app/seed-data/kesalahan_game.json')),

];
