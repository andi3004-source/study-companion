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

Jangan commit `.env`.

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
- Glos Inggris yang diawali "surname" atau mengandung "(slang)" tidak dipakai
  sebagai arti utama dan tidak diterjemahkan otomatis. Jika arti Indonesia
  belum ada, tampil "Arti belum tersedia".

**Keterbatasan:**

- Data terkoreksi masih berstatus **draf** dan belum ditinjau ulang.
- **HSK4-6 belum dikoreksi**, jadi masih bisa memuat homograf atau glos yang keliru.
- Arti Indonesia hasil `translate:meanings` adalah terjemahan mesin dari glos Inggris.
