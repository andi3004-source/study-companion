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
- **Sumber**: `error_analysis.sumber` berisi `langflow` atau `gemini`, yaitu
  analyzer yang benar-benar menghasilkan jawaban (jadi hasil cadangan tercatat
  sebagai `gemini`). Ringkasan sesi menampilkan "Dianalisis oleh IBM Langflow"
  atau "Dianalisis oleh Gemini (cadangan)". Sesi lama tanpa kunci ini tidak
  menampilkan label.
- **Permintaan Langflow**: sama dengan contoh di panel *API access* Langflow,
  yaitu `POST {LANGFLOW_URL}/api/v1/run/{LANGFLOW_FLOW_ID}?stream=false` dengan
  header `x-api-key` dan body `output_type`, `input_type`, `input_value`,
  `session_id`. `LANGFLOW_URL` cukup URL dasar; garis miring di akhir atau
  akhiran `/api` dibuang otomatis. Tiap analisis memakai `session_id` baru
  (`analisis-{id sesi}-{waktu}-{acak}`) supaya riwayat chat Langflow tidak
  tercampur antarsesi. `LANGFLOW_API_KEY` wajib untuk Langflow yang memakai
  autentikasi; header `x-api-key` hanya dikirim bila key terisi.
- **Timeout**: `LANGFLOW_TIMEOUT` (detik, bawaan 120). Satu analisis transkrip
  dengan banyak kesalahan pernah memakan sekitar 74 detik. Job punya batas
  waktu 180 detik: cukup untuk Langflow lalu cadangan Gemini.
- **Error Langflow di log**: respons non-2xx dicatat sebagai
  `Langflow HTTP {status}: {500 karakter pertama isi respons}`. Sebelum
  dipotong, isi itu disamarkan (`[REDACTED]`): nilai `LANGFLOW_API_KEY` dan
  `GEMINI_API_KEY`, serta apa pun yang mirip `sk-…`, `AIza…`, `AstraCS:…`.
  Header dan key tidak pernah dicatat.
- **Retry**: dengan worker antrean, kegagalan Langflow yang sementara (HTTP 429,
  502, 503, 504, atau teks "high demand"/"overloaded") diulang sampai 3
  percobaan dengan jeda 15 lalu 45 detik. Kegagalan permanen (401, 403, 422,
  kuota habis, JSON rusak, timeout koneksi) tidak diulang dan langsung jatuh ke
  Gemini. Percobaan terakhir juga jatuh ke Gemini. Dengan `QUEUE_CONNECTION=sync`
  tidak ada retry: langsung Langflow lalu Gemini, sehingga sesi tidak menggantung.
- **Gagal**: sesi dengan analisis gagal bisa dijalankan ulang dengan
  `php artisan analysis:retry-failed` (pakai `--dry-run` untuk melihat daftarnya dulu).

### Mendiagnosis Langflow (`langflow:ping`)

```bash
php artisan langflow:ping --config          # tanpa jaringan: apa yang terisi, timeout, URL akhir
php artisan langflow:ping                   # SATU panggilan sungguhan: status, waktu, teks hasil
php artisan langflow:ping --dump-structure  # + pohon kunci JSON respons, simpan respons tersamarkan
```

- `--config` tidak memanggil jaringan dan tidak pernah mencetak key (hanya
  "terisi, panjang N" atau "kosong").
- Tanpa `--config`, perintah ini mengirim transkrip pendek tetap ke Langflow dan
  **memakai kuota model**. Error ditampilkan dengan status HTTP dan isi respons
  yang sudah disamarkan, beserta jenisnya (sementara atau permanen).
- `--dump-structure` mencetak nama kunci dan nilai string (dipotong 80 karakter,
  disamarkan), lalu menyimpan seluruh respons yang sudah disamarkan ke
  `storage/app/langflow-last-response.json` (tidak ikut git). Periksa isinya,
  lalu salin ke `tests/Fixtures/langflow_response.json` sebagai fixture tes.

### Menjalankan analisis saat demo

Analisis berjalan sebagai job antrean. Ada dua cara:

1. **Disarankan:** `QUEUE_CONNECTION=database` (bawaan) dengan worker di terminal terpisah:

   ```bash
   php artisan queue:work
   ```

   Tanpa worker, job hanya menumpuk di tabel `jobs`. Ringkasan sesi berhenti
   menunggu setelah 150 detik dan menampilkan tombol **Muat ulang**; hasilnya
   juga muncul di Progress setelah worker selesai memprosesnya.
   `composer run dev` sudah menjalankan worker (`queue:listen`).

   **Anjuran: `DB_QUEUE_RETRY_AFTER=200`.** Nilai bawaannya 90 detik, padahal
   job analisis boleh berjalan sampai 180 detik. Antrean menganggap job yang
   belum selesai setelah `retry_after` detik sebagai macet dan menyerahkannya
   lagi ke worker, sehingga analisis yang lambat bisa jalan dua kali (dua
   panggilan AI). `retry_after` harus selalu lebih besar dari timeout job.

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

## Aksesibilitas

Status per butir di cabang `fitur-aksesibilitas`. **Selesai** berarti sudah dikerjakan dan markup HTML-nya dites otomatis (`AccessibilityChatTest`, `DisplaySettingsTest`, `BantuanPageTest`, `KataBicaraTest`). Itu belum berarti sudah diverifikasi dengan pembaca layar: pengujian manual oleh tim dengan Narator sedang berlangsung.

| Butir | Status | Catatan |
|---|---|---|
| `lang="id"`, tautan "Lewati ke konten" / "Lewati ke kolom tulis" | Selesai | Tautan tampil saat mendapat fokus. |
| Landmark header, nav (berlabel), main `#konten`, footer; satu `h1` | Sebagian | Ada di layout halaman belajar. Layout `app`/`guest` (profil, login) hanya mendapat `lang="id"`. |
| Kolom tulis chat: label, `aria-describedby`, Enter / Shift+Enter, IME | Selesai | Fokus tetap di kolom setelah kirim. |
| Percakapan `role="log"` + `aria-live="polite"`, nama pengirim, `lang` zh-Hans/en/id | Selesai | Pesan pengguna berhanzi diberi `zh-Hans`; pinyin tidak. |
| Daerah `role="status"` (Terkirim, membalas, analisis) | Selesai | Polling tidak memindahkan fokus. |
| Tombol bicara `aria-pressed`, tombol putar per balasan, konfirmasi "Selesai dan analisis" sebaris | Selesai | |
| Pengaturan tampilan: ukuran teks (3 tingkat), kontras tinggi, mode pembaca layar | Selesai | Disimpan di `localStorage`, diterapkan sebagai kelas pada `<html>`. Hanya di layout halaman belajar. |
| Teks dasar 18 px, fokus 3 px, target 44 px, `prefers-reduced-motion` | Sebagian | Aturan CSS global sudah ada; belum dicek visual di setiap halaman. |
| Kontras teks ≥ 4,5:1 | Sebagian | Warna `ink-soft`, `ink-faint`, `mandarin`, `english` digelapkan (≥ 5:1 di latar cream/putih). Halaman lain belum diaudit satu per satu. |
| Informasi tidak hanya lewat warna | Sebagian | Halaman aktif di navigasi diberi garis bawah + `aria-current`. Halaman lain belum diaudit. |
| Halaman Bantuan (`/bantuan`) dan pernyataan aksesibilitas (draf) | Selesai | |
| Kata bicara (`/kata-bicara`) | Selesai | Katalog `storage/app/seed-data/daftar_benda_tertutup.json` sudah ada di repo (di-commit). Jika berkas itu hilang atau rusak, halaman menampilkan "Katalog belum tersedia". Skema yang diharapkan ada di `config/katabicara.php`. |
| Halaman lain (Kartu, Kamus, Ujian, Isian, Game, Ucapan, Progress) | Belum diaudit | Hanya mendapat perubahan layout dan warna global. |
| Uji manual oleh tim dengan pembaca layar Narator | Sedang berlangsung | Belum selesai untuk butir mana pun di atas. |
| Uji dengan pengguna tunanetra | Belum dilakukan | |

**Batas:**

- Dirancang untuk pembaca layar. Pengujian oleh tim dengan Narator **sedang berlangsung**; **belum** dilakukan dengan pengguna tunanetra.
- Input dan output suara memakai Web Speech API dan hanya berjalan di **Chrome atau Edge**.
- Tes otomatis memeriksa markup HTML, bukan pengalaman nyata dengan pembaca layar.

## Game inklusif

Halaman `/game` berisi kartu permainan (`App\Livewire\Games\Hub`; tambah game
dengan menambah satu entri di `games()`). Tautan "Game" di navigasi membuka
halaman ini. Semua game tanpa panggilan AI.

- **Dengar & Pilih** (`/game/dengar-pilih`): 5 soal per sesi. Kata diambil dari
  kosakata HSK 1 yang punya arti bahasa Indonesia (tabel yang sama dengan
  Kartu, satu baris per hanzi) dan, bila ada, daftar benda tertutup
  (`ikut_prompt: true`). Hanzi dan pinyin selalu tampil sebagai teks; kata
  diucapkan dengan suara browser (tidak otomatis bila Mode pembaca layar
  aktif), ada tombol "Dengarkan lagi" dan pilihan kecepatan suara. Empat
  pilihan arti (pengecoh dari level yang sama). Jawaban tidak disimpan ke
  basis data: `game_attempts` dipakai Progress sebagai kategori kesalahan
  Pemburu Kesalahan, jadi jawaban kosakata tidak dicampur ke sana.
- **Pemburu Kesalahan** (`/game/pemburu-kesalahan`): alur dan penyimpanan
  jawaban tidak berubah; pilihan kini berupa radio dalam fieldset dan ada
  tombol "Dengarkan kalimat". Soalnya dibaca dari
  `storage/app/seed-data/kesalahan_game.json` (`GAME_ITEMS_PATH`); berkas itu
  belum ada di repo, jadi tanpa berkas tersebut halaman hanya menampilkan
  pesan bahwa soal belum tersedia.

**Dirancang agar dapat dimainkan dengan keyboard, suara, dan tampilan
diperbesar:** pilihan berupa radio berlabel dalam fieldset (panah untuk
memilih, Enter untuk menjawab), tanpa batas waktu dan tanpa seret-lepas,
umpan balik berupa teks dengan simbol ✓/✗ yang diumumkan (`role="status"`),
fokus pindah ke tombol berikutnya setelah menjawab, hanzi berukuran 4rem dan
tombol jawaban minimal 3,5rem yang ikut membesar mengikuti ukuran teks, satu
kolom, serta opsi "Jarak baris dan huruf lebih lega" di panel Tampilan.

**Batas:**

- **Belum** diuji dengan pengguna tunanetra atau pengguna lemah penglihatan.
  Tes otomatis hanya memeriksa markup HTML.
- Hasil Dengar & Pilih **tidak masuk halaman Progress**. Ini disengaja: Progress
  membaca `game_attempts` sebagai kategori kesalahan Pemburu Kesalahan, dan
  jawaban kosakata akan mencemari kategori itu.
- Suara memakai suara bawaan browser (Web Speech API); kualitasnya bervariasi
  antarperangkat, dan suara Mandarin harus terpasang di perangkat agar kata
  Mandarin terdengar benar.
- Panel Tampilan masih punya tiga tingkat ukuran teks (Normal, Besar, Sangat
  besar). Tingkat keempat belum ditambahkan.
- Rencana: tahap benar atau salah sebelum memilih kategori, untuk mengurangi
  beban metabahasa; belum dibangun.

## Raba lalu dengar

Pelajar memotret satu benda yang dipegang dengan kamera bawaan perangkat, lalu
aplikasi menampilkan dan membacakan namanya dalam bahasa Indonesia, Inggris,
dan Mandarin (`/scan-benda`).

- **Dimatikan secara default.** Aktifkan dengan `SCAN_BENDA_ENABLED=true`.
  Selama mati, `/scan-benda` dan `POST /scan-benda/kenali` menjawab 404 dan
  tautannya tidak tampil di navigasi. Pengaturan lain ada di `config/scan.php`
  (`SCAN_BENDA_MAX_IMAGE_BYTES`, `SCAN_BENDA_RATE_LIMIT_PER_MINUTE`,
  `SCAN_BENDA_TIMEOUT`).
- **Daftar tertutup.** Model visi (Gemini, memakai `GEMINI_API_KEY` dan
  `GEMINI_MODEL`) hanya boleh memilih `benda_id` dari entri `ikut_prompt: true`
  di `storage/app/seed-data/daftar_benda_tertutup.json` (lewat
  `KataBicaraCatalog`), atau menjawab "tidak yakin" atau "perlu foto ulang".
  Nama Indonesia, Inggris, hanzi, pinyin, dan IPA selalu diambil dari katalog,
  tidak pernah dari teks model. `benda_id` di luar daftar dianggap "tidak
  yakin". Tanpa `GEMINI_API_KEY`, aplikasi selalu menjawab "tidak yakin"
  (catatan: model visi belum terhubung).
- **Hanya keyakinan tinggi yang diumumkan pasti.** Keyakinan sedang atau rendah
  diumumkan sebagai "Mungkin …" dengan tawaran foto ulang.
- **Foto tidak disimpan.** Foto dikompres di browser (sisi terpanjang sekitar
  1024 px, JPEG), dikirim sebagai base64 di badan JSON, diproses di memori,
  lalu dibuang. Tidak ada berkas gambar di storage, tidak ada kolom basis
  data, tidak memakai unggahan sementara Livewire, dan gambar maupun teks
  model tidak ditulis ke log. Foto memang dikirim ke layanan Gemini untuk
  dikenali; pengguna harus mencentang persetujuan dulu.
- **Bukan alat keselamatan atau medis.** Jangan dipakai untuk obat atau benda
  berbahaya; peringatan ini tampil di halaman.
- **Deskripsi bentuk adalah perkiraan** dari foto dan mungkin tidak persis
  dengan perabaan.
- **Status uji:** baru diuji dengan tes otomatis memakai pengenal palsu dan
  `Http::fake`. **Belum** diuji dengan foto sungguhan maupun dengan model visi
  yang benar-benar terhubung, dan belum diuji dengan pengguna tunanetra.

## Akun demo

Akun siap pakai untuk peninjau yang menjalankan aplikasi **secara lokal**. Akun ini mati secara bawaan.

1. Di `.env`, isi:

   ```
   DEMO_ACCOUNT_ENABLED=true
   DEMO_ACCOUNT_PASSWORD=<kata sandi khusus demo>
   # opsional, bawaan demo@studycompanion.test
   DEMO_ACCOUNT_EMAIL=demo@studycompanion.test
   ```

2. Jalankan `php artisan db:seed --class=DemoUserSeeder`. Perintah ini membuat atau memperbarui pengguna "Akun Demo" dengan email terverifikasi dan onboarding selesai. Tidak ada data sesi atau latihan palsu. Bila akun demo aktif, `php artisan db:seed` juga memanggil seeder ini.
3. Halaman login menampilkan kotak "Akun demo (hanya lingkungan lokal)" berisi email, kata sandi, dan tombol **Isi otomatis**. Tombol itu hanya mengisi kolom, tidak mengirim formulir.

**Peringatan:**

- Hanya untuk lingkungan lokal. Seeder menolak berjalan di `APP_ENV=production`, dan kotak login tidak dirender di production. Bila akun demo mati, kotak itu tidak meninggalkan jejak di HTML.
- Kata sandi tampil apa adanya di halaman login. Pakai kata sandi **khusus demo** dan **jangan pernah** dipakai di tempat lain.
- Kata sandi hanya dibaca dari `.env`; tidak ada nilai bawaan di kode. Bila kosong, seeder berhenti dengan pesan galat.
