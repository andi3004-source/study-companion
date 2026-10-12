{{--
    Public home page for visitors who are not logged in. Static on purpose:
    no AI features, no Livewire, no app.js, and no requests to outside
    services (welcome.css has no web-font import).
--}}
@php
    $fitur = [
        ['nama' => 'Ngobrol dengan AI', 'status' => 'Berjalan', 'isi' => 'Latihan percakapan Mandarin atau Inggris sesuai skenario, dengan koreksi singkat dalam bahasa Indonesia.'],
        ['nama' => 'Analisis kesalahan', 'status' => 'Eksperimen', 'isi' => 'Setelah sesi selesai, kesalahan dikelompokkan per jenis dan ditampilkan di halaman Progress.'],
        ['nama' => 'Kartu, Kamus, Ujian, dan Isian', 'status' => 'Berjalan', 'isi' => 'Latihan kosakata HSK dan IELTS dengan jadwal harian.'],
        ['nama' => 'Arti Indonesia untuk kosakata HSK', 'status' => 'Draf', 'isi' => 'Arti yang sudah dikoreksi diberi tanda draf sampai diperiksa penutur.'],
        ['nama' => 'Game Pemburu Kesalahan', 'status' => 'Draf', 'isi' => 'Tebak jenis kesalahan dalam kalimat Mandarin. Sebagian soal masih diverifikasi.'],
        ['nama' => 'Cek Pengucapan', 'status' => 'Eksperimen', 'isi' => 'Rekam satu kata dan bandingkan dengan target. Hasilnya bisa meleset karena bergantung pada pengenal suara browser.'],
        ['nama' => 'Kata bicara', 'status' => 'Draf', 'isi' => 'Cari satu kata dan lihat dalam bahasa Indonesia, Inggris, dan Mandarin. Daftar katanya masih perlu dicek penutur.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Study Companion</title>
    <meta name="description" content="Latihan Mandarin dan Inggris lewat ngobrol, analisis kesalahan, dan latihan terarah.">
    @vite(['resources/css/welcome.css'])
</head>
<body class="bg-cream text-ink antialiased">
    <a href="#konten" class="skip-link">Lewati ke konten</a>

    <header class="bg-navy text-white">
        <div class="max-w-5xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <a href="/" class="flex items-center gap-2 min-h-[44px] text-white no-underline">
                <span aria-hidden="true" class="w-10 h-10 rounded-xl bg-orange flex items-center justify-center font-bold text-xl text-navy">好</span>
                <span class="font-bold text-lg">Study Companion</span>
            </a>
            <nav aria-label="Akun" class="flex flex-wrap items-center gap-2">
                <a href="{{ route('login') }}" class="inline-flex items-center min-h-[44px] px-4 rounded-xl font-bold text-sm text-white underline underline-offset-4">Masuk</a>
                <a href="{{ route('register') }}" class="inline-flex items-center min-h-[44px] px-4 rounded-xl bg-orange text-navy font-bold text-sm">Daftar</a>
            </nav>
        </div>
    </header>

    <main id="konten" tabindex="-1" class="focus:outline-none">
        {{-- Hero --}}
        <section aria-labelledby="judul-utama" class="max-w-5xl mx-auto px-4 py-12 sm:py-16">
            <h1 id="judul-utama" class="text-3xl sm:text-4xl font-bold text-navy leading-tight max-w-3xl">
                Latihan Mandarin dan Inggris lewat ngobrol, lalu belajar dari kesalahanmu sendiri.
            </h1>
            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center min-h-[44px] px-6 py-3 rounded-xl bg-navy text-white font-bold">Masuk atau daftar</a>
                <a href="#cara-kerja" class="inline-flex items-center justify-center min-h-[44px] px-6 py-3 rounded-xl border-2 border-navy text-navy font-bold">Lihat cara kerja</a>
            </div>
        </section>

        {{-- Problem --}}
        <section aria-labelledby="judul-masalah" class="bg-card border-y border-line">
            <div class="max-w-5xl mx-auto px-4 py-12">
                <h2 id="judul-masalah" class="text-2xl font-bold text-navy">Masalahnya</h2>
                <p class="mt-3 max-w-3xl">Banyak daftar kosakata HSK hanya berisi arti dalam bahasa Inggris. Kami mengaudit data yang kami pakai dan menemukan:</p>
                <ul class="mt-6 grid gap-4 sm:grid-cols-2">
                    <li class="rounded-2xl border-2 border-navy p-5">
                        <p class="text-3xl font-bold text-navy">0 dari 4.991</p>
                        <p class="mt-1">entri kosakata HSK di data sumber kami punya arti dalam bahasa Indonesia.</p>
                    </li>
                    <li class="rounded-2xl border-2 border-navy p-5">
                        <p class="text-3xl font-bold text-navy">Sekitar 16%</p>
                        <p class="mt-1">sampel entri HSK 1–3 yang kami periksa ternyata keliru.</p>
                    </li>
                </ul>
                <p class="mt-4 text-sm text-ink-soft">Angka dari audit internal tim terhadap data yang dipakai aplikasi ini.</p>
            </div>
        </section>

        {{-- How it works --}}
        <section id="cara-kerja" aria-labelledby="judul-cara-kerja" class="max-w-5xl mx-auto px-4 py-12 scroll-mt-4">
            <h2 id="judul-cara-kerja" class="text-2xl font-bold text-navy">Cara kerja</h2>
            <ol class="mt-6 grid gap-4 sm:grid-cols-3">
                <li class="rounded-2xl bg-card border border-line p-5">
                    <h3 class="font-bold text-lg"><span class="text-navy">1.</span> Ngobrol</h3>
                    <p class="mt-2">Pilih skenario atau topik bebas, lalu ngobrol dengan AI dalam bahasa Mandarin atau Inggris, lewat ketikan atau suara.</p>
                </li>
                <li class="rounded-2xl bg-card border border-line p-5">
                    <h3 class="font-bold text-lg"><span class="text-navy">2.</span> Analisis kesalahan</h3>
                    <p class="mt-2">Setelah sesi selesai, kesalahanmu dikelompokkan per jenis, lengkap dengan contoh dan perbaikannya.</p>
                </li>
                <li class="rounded-2xl bg-card border border-line p-5">
                    <h3 class="font-bold text-lg"><span class="text-navy">3.</span> Latihan</h3>
                    <p class="mt-2">Latih jenis kesalahan yang paling sering muncul lewat kartu kata, isian, ujian, dan game.</p>
                </li>
            </ol>
        </section>

        {{-- Features --}}
        <section aria-labelledby="judul-fitur" class="bg-card border-y border-line">
            <div class="max-w-5xl mx-auto px-4 py-12">
                <h2 id="judul-fitur" class="text-2xl font-bold text-navy">Fitur dan statusnya</h2>
                <p class="mt-3 max-w-3xl">Status ditulis apa adanya: <strong>Berjalan</strong> bisa dipakai sekarang, <strong>Draf</strong> datanya masih diperiksa, <strong>Eksperimen</strong> hasilnya bisa berubah.</p>
                <ul class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ($fitur as $item)
                        <li class="rounded-2xl border border-line p-5">
                            <h3 class="font-bold text-lg">{{ $item['nama'] }}</h3>
                            <p class="mt-1"><span class="inline-block rounded-full border-2 border-navy px-3 py-0.5 text-sm font-bold text-navy">Status: {{ $item['status'] }}</span></p>
                            <p class="mt-2">{{ $item['isi'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- Accessibility --}}
        <section aria-labelledby="judul-aksesibilitas" class="max-w-5xl mx-auto px-4 py-12">
            <h2 id="judul-aksesibilitas" class="text-2xl font-bold text-navy">Aksesibilitas</h2>
            <p class="mt-3 max-w-3xl">Aplikasi ini dirancang agar bisa dipakai dengan keyboard dan pembaca layar: ada tautan lewati, label pada setiap kolom, status yang diumumkan, dan pengaturan ukuran teks serta kontras tinggi.</p>
            <p class="mt-3 max-w-3xl">Pengujian sejauh ini dilakukan oleh tim (keyboard dan pembaca layar Narator) dan masih berlangsung. Aplikasi ini <strong>belum</strong> diuji dengan pengguna tunanetra.</p>
        </section>

        {{-- AI use in development --}}
        <section aria-labelledby="judul-ai" class="bg-card border-y border-line">
            <div class="max-w-5xl mx-auto px-4 py-12">
                <h2 id="judul-ai" class="text-2xl font-bold text-navy">Penggunaan AI dalam pengerjaan</h2>
                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-line p-5">
                        <dt class="font-bold">Claude Code</dt>
                        <dd class="mt-1">Membantu menulis, menguji, dan merapikan kode aplikasi.</dd>
                    </div>
                    <div class="rounded-2xl border border-line p-5">
                        <dt class="font-bold">Claude</dt>
                        <dd class="mt-1">Membantu diskusi rancangan dan penyusunan teks.</dd>
                    </div>
                    <div class="rounded-2xl border border-line p-5">
                        <dt class="font-bold">Gemini</dt>
                        <dd class="mt-1">Model AI untuk teman ngobrol di aplikasi, dan cadangan untuk analisis kesalahan.</dd>
                    </div>
                    <div class="rounded-2xl border border-line p-5">
                        <dt class="font-bold">IBM Langflow dan Astra DB</dt>
                        <dd class="mt-1">Alur analisis kesalahan sesi Ngobrol dibangun di IBM Langflow, dengan Astra DB sebagai penyimpan data alurnya.</dd>
                    </div>
                    <div class="rounded-2xl border border-line p-5">
                        <dt class="font-bold">IBM Bob</dt>
                        <dd class="mt-1">Membantu pengodean saat menyambungkan aplikasi ke Langflow.</dd>
                    </div>
                    <div class="rounded-2xl border border-line p-5">
                        <dt class="font-bold">Peran tim</dt>
                        <dd class="mt-1">Tim menentukan fitur, mengaudit dan mengoreksi data HSK, menguji dengan keyboard dan pembaca layar, serta meninjau setiap perubahan yang dibuat dengan bantuan AI.</dd>
                    </div>
                </dl>
            </div>
        </section>

        {{-- FAQ --}}
        <section aria-labelledby="judul-faq" class="max-w-5xl mx-auto px-4 py-12">
            <h2 id="judul-faq" class="text-2xl font-bold text-navy">Pertanyaan umum</h2>
            <div class="mt-6 space-y-6 max-w-3xl">
                <div>
                    <h3 class="font-bold text-lg">Data apa yang disimpan?</h3>
                    <p class="mt-1">Nama, email, dan kata sandi akunmu (kata sandi disimpan dalam bentuk hash), progres belajar, isi percakapan Ngobrol beserta hasil analisisnya, catatan, dan jawaban latihan. Isi percakapan dikirim ke layanan AI untuk diproses.</p>
                </div>
                <div>
                    <h3 class="font-bold text-lg">Apakah foto disimpan?</h3>
                    <p class="mt-1">Tidak. Foto tidak disimpan.</p>
                </div>
                <div>
                    <h3 class="font-bold text-lg">Browser apa yang dibutuhkan untuk suara?</h3>
                    <p class="mt-1">Input dan output suara memakai Web Speech API, jadi gunakan Chrome atau Edge. Fitur lain bisa dipakai di browser modern apa pun.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-navy text-white">
        <div class="max-w-5xl mx-auto px-4 py-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p>Study Companion</p>
            <p><a href="{{ route('bantuan') }}" class="underline underline-offset-4 inline-flex items-center min-h-[44px]">Bantuan</a> <span class="text-sm">(perlu masuk dulu)</span></p>
        </div>
    </footer>
</body>
</html>
