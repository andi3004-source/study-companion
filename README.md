# Web Belajar Bahasa

Aplikasi belajar mandiri Mandarin (HSK) dan Inggris (IELTS) dengan kurikulum
harian. Dibangun dengan Laravel 12, Livewire 3 (Volt), Tailwind, dan SQLite.

## Fitur

- **Dashboard harian**: checklist per hari dari kurikulum HSK1 (150 kata dalam batch) dan topik IELTS.
- **Kartu**: flashcard Mandarin (HSK1-6), irregular verbs, dan kosakata IELTS, dengan tanda "sudah hafal".
- **Kamus**: pencarian Mandarin (hanzi, pinyin tanpa nada, arti) dan Inggris (CEFR + irregular verbs).
- **Latihan Isian**: isi pinyin, hanzi, atau arti; pinyin dinilai tanpa memperhatikan nada.
- **Ujian**: kuis yang dibuat dari kosakata.
- **Ngobrol (Conversation Partner)**: percakapan dengan AI (Gemini).
- **Cek Pelafalan**: lewat microservice `pronunciation-service/`, atau STT lokal sebagai cadangan.
- **Progres**: ringkasan kemajuan belajar.

## Menjalankan secara lokal

Butuh PHP 8.2+, Composer, Node.js, dan ekstensi `pdo_sqlite`.

```bash
composer install
cp .env.example .env           # lalu isi variabel yang diperlukan (lihat di bawah)
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
composer run dev               # server, queue worker, log, dan Vite sekaligus
```

`composer run setup` menjalankan sebagian besar langkah di atas sekaligus.
Terjemahan arti lewat AI tidak berjalan otomatis. Jalankan
`php artisan translate:meanings` dengan `queue:work` aktif.

## Menjalankan tes

```bash
php artisan test
```

Tes memakai SQLite in-memory (lihat `phpunit.xml`), jadi database lokal tidak tersentuh.

## Variabel `.env`

Lihat `.env.example` untuk keterangan dan nilai bawaan. Variabel utama:

| Variabel | Keterangan |
| --- | --- |
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Pengaturan dasar Laravel |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | Bahasa aplikasi |
| `DB_CONNECTION` | `sqlite` |
| `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE` | Driver sesi, antrean, dan cache |
| `MAIL_*` | Pengiriman email (verifikasi, reset password) |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Provider AI aktif: percakapan dan `translate:meanings` |
| `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` | Provider AI alternatif (tidak aktif) |
| `PRONUNCIATION_SERVICE_URL`, `PRONUNCIATION_CHECKER_DRIVER` | Layanan cek pelafalan (`ml` atau `stt_match`) |
| `HSK_CORRECTIONS_PATH` | Opsional: path file koreksi HSK2-3 |
| `HSK_LEVEL_LOCK` | `true`/`false`: kunci percakapan Mandarin ke kosakata HSK 1 |

Jangan commit `.env`.

## Partner percakapan (Ngobrol)

- **Suara**: input suara (mic) memakai Web Speech API bawaan browser, jadi hanya
  berjalan di **Chrome atau Edge**. Di browser lain, tetap bisa mengetik. Setiap
  pesan user dicatat asalnya di `conversation_turns.input_mode` (`text` atau `voice`).
- **Kunci level HSK 1** (`HSK_LEVEL_LOCK=true`, bawaan): sesi Mandarin mendapat
  daftar 150 hanzi HSK 1 beserta instruksi untuk hanya memakai kata dari daftar
  itu, dan menulis kalimat Mandarin tanpa pinyin, kurung, atau terjemahan (karena
  dibacakan text-to-speech). Halaman chat menampilkan lencana "Level: HSK 1 (terkunci)".
  - **Biaya**: tiap pesan Mandarin menambah sekitar **1.074 karakter atau ±440 token
    input** (daftarnya sendiri 378 karakter, ±270 token). Ini perkiraan kasar, bukan
    hitungan tokenizer; cek ulang dengan `php artisan hsk:prompt-stats`. Token input
    yang sebenarnya per panggilan tercatat di `conversation_api_calls.tokens_in`.
  - `HSK_LEVEL_LOCK=false` mematikan fitur ini, dan biaya tambahan itu ikut hilang.
  - **Keterbatasan**: kunci ini bekerja lewat instruksi di prompt. Sifatnya imbauan
    kuat, **bukan jaminan**; model tetap bisa sesekali memakai kata di luar HSK 1.

## Analisis kesalahan ("Kesalahan terbanyakmu")

Saat sesi percakapan **Mandarin** diselesaikan, transkripnya dianalisis AI di
latar belakang (`AnalyzeSessionJob`). Tiap kesalahan diberi satu kategori tetap
(urutan kata, 是 dengan kata sifat, 不 atau 没, kata penggolong, nada,
pelafalan, pilihan kata, lainnya). Hasilnya tampil di ringkasan sesi, dan tiga
kategori teratas 7 hari terakhir tampil di halaman Progress.

- **Analyzer**: memakai flow IBM Langflow bila `LANGFLOW_URL` dan
  `LANGFLOW_FLOW_ID` terisi. Kalau Langflow error, timeout, atau mengembalikan
  JSON rusak, analisis otomatis jatuh ke Gemini. Tanpa Langflow, langsung Gemini.
  Kalau keduanya tidak terkonfigurasi, analisis tidak dijalankan dan bagiannya
  tidak ditampilkan. Setiap analisis memakai satu panggilan AI.
- **Gagal**: sesi dengan analisis gagal bisa dijalankan ulang dengan
  `php artisan analysis:retry-failed` (pakai `--dry-run` untuk melihat daftarnya dulu).

### Menjalankan analisis saat demo

Analisis berjalan sebagai job antrean. Ada dua cara:

1. **Disarankan:** `QUEUE_CONNECTION=database` (bawaan) dengan worker di terminal terpisah:

   ```bash
   php artisan queue:work
   ```

   Tanpa worker, job hanya menumpuk di tabel `jobs`. Ringkasan sesi berhenti
   menunggu setelah 60 detik, dan hasilnya baru muncul (di Progress) setelah
   worker dijalankan. `composer run dev` sudah menjalankan worker (`queue:listen`).

2. **`QUEUE_CONNECTION=sync`:** job dijalankan setelah respons dikirim
   (`afterResponse`), tanpa worker. Catatan: `php artisan serve` hanya
   memproses **satu permintaan sekaligus** kecuali `PHP_CLI_SERVER_WORKERS`
   diatur (misalnya `PHP_CLI_SERVER_WORKERS=4`). Jadi selama analisis berjalan
   (bisa sampai puluhan detik), polling dan halaman lain bisa tertahan.

## Data HSK dan koreksinya

Kosakata HSK1-6 diimpor dari `storage/app/seed-data/mandarin_hsk1to6_complete.json`.
Untuk sebagian kata, dataset ini memilih homograf yang keliru, misalnya 都
"Dū, surname Du" (seharusnya dōu) atau 听 "yǐn, smile (archaic)" (seharusnya tīng).

- **HSK1 (kurikulum, `mandarin_words`)**: pinyin dan arti diambil dari data kurasi
  `database/seeders/data/`. Seeder HSK hanya menambah kolom pelengkap.
  Basis data yang sudah terlanjur tertimpa dipulihkan dengan
  `php artisan hsk:repair-curriculum` (pakai `--dry-run` untuk melihat perubahan dulu).
- **HSK2-3 (`mandarin_hsk_words`)**: dikoreksi dari
  `storage/app/seed-data/hsk1to3_kosakata_id.json` dengan
  `php artisan hsk:apply-corrections` (juga ada `--dry-run`). Baris yang dikoreksi
  ditandai `data_status = corrected_draft` dan tampil dengan lencana
  "Data dikoreksi (draf)" di Kamus dan Kartu.
- Glos Inggris yang **arti pertamanya** (bagian sebelum `;` pertama) diawali
  "surname" atau "(slang)" tidak dipakai sebagai arti utama dan tidak
  diterjemahkan otomatis. Arti slang di urutan berikutnya tidak berpengaruh,
  misalnya 机场 "airport; …(slang)…". Jika arti Indonesia belum ada, tampil
  "Arti belum tersedia". Terjemahan lama dari glos seperti itu bisa dikosongkan
  dengan `php artisan hsk:clear-unreliable-translations` (ada `--dry-run`).
- `translate:meanings` mengirim hanzi dan pinyin bersama glos Inggris, supaya
  model menerjemahkan makna HSK yang dimaksud.

**Keterbatasan:**

- Data terkoreksi masih berstatus **draf** dan belum ditinjau ulang.
- **HSK4-6 belum dikoreksi**, jadi masih bisa memuat homograf atau glos yang keliru.
- Arti Indonesia hasil `translate:meanings` adalah terjemahan mesin dari glos Inggris.
